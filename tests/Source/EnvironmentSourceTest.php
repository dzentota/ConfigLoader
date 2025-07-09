<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Source;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Source\EnvironmentSource;
use dzentota\ConfigLoader\Exception\SourceException;

class EnvironmentSourceTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Backup original environment
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        // Restore original environment
        $_ENV = $this->originalEnv;
        
        // Clean up any test environment variables
        $testVars = [
            'TEST_PORT', 'TEST_DEBUG', 'TEST_DATABASE_HOST',
            'APP_PORT', 'APP_DEBUG', 'APP_FEATURE_X',
            'NO_PREFIX_VAR', 'DOCKER_SECRET', 'COMPLEX_VAR'
        ];
        
        foreach ($testVars as $var) {
            unset($_ENV[$var]);
            if (getenv($var) !== false) {
                putenv($var);
            }
        }
        
        parent::tearDown();
    }

    public function testConstructor(): void
    {
        $source = new EnvironmentSource('TEST_', 50);
        
        $this->assertEquals(50, $source->getPriority());
        $this->assertEquals('TEST_', $source->getPrefix());
        $this->assertEquals('Environment Variables (prefix: TEST_)', $source->getName());
    }

    public function testConstructorWithDefaults(): void
    {
        $source = new EnvironmentSource();
        
        $this->assertEquals(100, $source->getPriority()); // Default priority
        $this->assertEquals('', $source->getPrefix()); // Default prefix
        $this->assertEquals('Environment Variables', $source->getName()); // Default name
    }

    public function testConstructorWithoutPrefix(): void
    {
        $source = new EnvironmentSource('', 75);
        
        $this->assertEquals(75, $source->getPriority());
        $this->assertEquals('', $source->getPrefix());
        $this->assertEquals('Environment Variables', $source->getName());
    }

    public function testLoadWithPrefix(): void
    {
        $_ENV['TEST_PORT'] = '8080';
        $_ENV['TEST_DEBUG'] = 'true';
        $_ENV['TEST_DATABASE_HOST'] = 'localhost';
        $_ENV['OTHER_VAR'] = 'should_not_be_loaded';
        
        $source = new EnvironmentSource('TEST_');
        $data = $source->load();
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('PORT', $data);
        $this->assertArrayHasKey('DEBUG', $data);
        $this->assertArrayHasKey('DATABASE_HOST', $data);
        $this->assertArrayNotHasKey('OTHER_VAR', $data);
        
        $this->assertEquals('8080', $data['PORT']);
        $this->assertEquals('true', $data['DEBUG']);
        $this->assertEquals('localhost', $data['DATABASE_HOST']);
    }

    public function testLoadWithoutPrefix(): void
    {
        $_ENV['PORT'] = '9090';
        $_ENV['DEBUG'] = 'false';
        $_ENV['SOME_VAR'] = 'value';
        
        $source = new EnvironmentSource('');
        $data = $source->load();
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('PORT', $data);
        $this->assertArrayHasKey('DEBUG', $data);
        $this->assertArrayHasKey('SOME_VAR', $data);
        
        $this->assertEquals('9090', $data['PORT']);
        $this->assertEquals('false', $data['DEBUG']);
        $this->assertEquals('value', $data['SOME_VAR']);
    }

    public function testLoadWithEmptyEnvironment(): void
    {
        // Clear all test environment variables
        $_ENV = [];
        
        $source = new EnvironmentSource('TEST_');
        $data = $source->load();
        
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testLoadCaching(): void
    {
        $_ENV['TEST_PORT'] = '8080';
        
        $source = new EnvironmentSource('TEST_');
        
        // First load
        $data1 = $source->load();
        $this->assertArrayHasKey('PORT', $data1);
        $this->assertEquals('8080', $data1['PORT']);
        
        // Change environment variable
        $_ENV['TEST_PORT'] = '9090';
        
        // Second load (should return cached data)
        $data2 = $source->load();
        $this->assertEquals('8080', $data2['PORT']); // Should still be cached value
        
        // Clear cache and load again
        $source->clearCache();
        $data3 = $source->load();
        $this->assertEquals('9090', $data3['PORT']); // Should now see new value
    }

    public function testHasWithPrefix(): void
    {
        $_ENV['TEST_EXISTING'] = 'value';
        
        $source = new EnvironmentSource('TEST_');
        
        $this->assertTrue($source->has('EXISTING'));
        $this->assertFalse($source->has('NON_EXISTING'));
    }

    public function testHasWithoutPrefix(): void
    {
        $_ENV['EXISTING_VAR'] = 'value';
        
        $source = new EnvironmentSource('');
        
        $this->assertTrue($source->has('EXISTING_VAR'));
        $this->assertFalse($source->has('NON_EXISTING_VAR'));
    }

    public function testHasWithGetenv(): void
    {
        // Set using putenv (not $_ENV)
        putenv('TEST_GETENV_VAR=test_value');
        
        $source = new EnvironmentSource('TEST_');
        
        $this->assertTrue($source->has('GETENV_VAR'));
        
        // Clean up
        putenv('TEST_GETENV_VAR');
    }

    public function testGetWithPrefix(): void
    {
        $_ENV['TEST_PORT'] = '8080';
        $_ENV['TEST_DEBUG'] = 'true';
        
        $source = new EnvironmentSource('TEST_');
        
        $this->assertEquals('8080', $source->get('PORT'));
        $this->assertEquals('true', $source->get('DEBUG'));
    }

    public function testGetWithoutPrefix(): void
    {
        $_ENV['PORT'] = '9090';
        $_ENV['DEBUG'] = 'false';
        
        $source = new EnvironmentSource('');
        
        $this->assertEquals('9090', $source->get('PORT'));
        $this->assertEquals('false', $source->get('DEBUG'));
    }

    public function testGetWithGetenv(): void
    {
        // Set using putenv (not $_ENV)
        putenv('TEST_PUTENV_VAR=putenv_value');
        
        $source = new EnvironmentSource('TEST_');
        
        $this->assertEquals('putenv_value', $source->get('PUTENV_VAR'));
        
        // Clean up
        putenv('TEST_PUTENV_VAR');
    }

    public function testGetThrowsExceptionForNonExistentKey(): void
    {
        $source = new EnvironmentSource('TEST_');
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Environment variable "TEST_NON_EXISTENT" not found');
        
        $source->get('NON_EXISTENT');
    }

    public function testGetSourceExceptionDetails(): void
    {
        $source = new EnvironmentSource('TEST_');
        
        try {
            $source->get('MISSING_VAR');
            $this->fail('Expected SourceException was not thrown');
        } catch (SourceException $e) {
            $this->assertEquals('TEST_MISSING_VAR', $e->getSourceName());
            $this->assertEquals('environment', $e->getSourceType());
            $this->assertStringContainsString('Environment variable "TEST_MISSING_VAR" not found', $e->getMessage());
        }
    }

    public function testGetNameWithPrefix(): void
    {
        $source = new EnvironmentSource('APP_');
        $this->assertEquals('Environment Variables (prefix: APP_)', $source->getName());
    }

    public function testGetNameWithoutPrefix(): void
    {
        $source = new EnvironmentSource('');
        $this->assertEquals('Environment Variables', $source->getName());
    }

    public function testClearCache(): void
    {
        $_ENV['TEST_VAR'] = 'initial';
        
        $source = new EnvironmentSource('TEST_');
        
        // Load and verify initial value
        $data = $source->load();
        $this->assertEquals('initial', $data['VAR']);
        
        // Change environment variable
        $_ENV['TEST_VAR'] = 'updated';
        
        // Should still return cached value
        $data = $source->load();
        $this->assertEquals('initial', $data['VAR']);
        
        // Clear cache
        $source->clearCache();
        
        // Should now return updated value
        $data = $source->load();
        $this->assertEquals('updated', $data['VAR']);
    }

    public function testSetPrefix(): void
    {
        $_ENV['OLD_PREFIX_VAR'] = 'old_value';
        $_ENV['NEW_PREFIX_VAR'] = 'new_value';
        
        $source = new EnvironmentSource('OLD_PREFIX_');
        
        // Initially should find old prefix variable
        $this->assertTrue($source->has('VAR'));
        $this->assertEquals('old_value', $source->get('VAR'));
        
        // Change prefix
        $source->setPrefix('NEW_PREFIX_');
        
        // Should now find new prefix variable
        $this->assertTrue($source->has('VAR'));
        $this->assertEquals('new_value', $source->get('VAR'));
        
        // Should not find old prefix variable
        $this->assertFalse($source->has('OLD_PREFIX_VAR'));
    }

    public function testSetPrefixClearsCache(): void
    {
        $_ENV['OLD_VAR'] = 'old_value';
        $_ENV['NEW_VAR'] = 'new_value';
        
        $source = new EnvironmentSource('OLD_');
        
        // Load with old prefix
        $data = $source->load();
        $this->assertArrayHasKey('VAR', $data);
        $this->assertEquals('old_value', $data['VAR']);
        
        // Change prefix
        $source->setPrefix('NEW_');
        
        // Load should reflect new prefix (cache should be cleared)
        $data = $source->load();
        $this->assertArrayHasKey('VAR', $data);
        $this->assertEquals('new_value', $data['VAR']);
    }

    public function testGetPrefix(): void
    {
        $source = new EnvironmentSource('MY_PREFIX_');
        $this->assertEquals('MY_PREFIX_', $source->getPrefix());
        
        $source->setPrefix('NEW_PREFIX_');
        $this->assertEquals('NEW_PREFIX_', $source->getPrefix());
    }

    public function testEnvironmentVariableTypes(): void
    {
        $_ENV['TEST_STRING'] = 'text_value';
        $_ENV['TEST_NUMBER'] = '42';
        $_ENV['TEST_BOOLEAN'] = 'true';
        $_ENV['TEST_EMPTY'] = '';
        $_ENV['TEST_SPACES'] = '  spaced  ';
        
        $source = new EnvironmentSource('TEST_');
        
        // All environment variables are strings
        $this->assertEquals('text_value', $source->get('STRING'));
        $this->assertEquals('42', $source->get('NUMBER'));
        $this->assertEquals('true', $source->get('BOOLEAN'));
        $this->assertEquals('', $source->get('EMPTY'));
        $this->assertEquals('  spaced  ', $source->get('SPACES'));
    }

    public function testSpecialCharactersInValues(): void
    {
        $_ENV['TEST_SPECIAL'] = 'value with spaces and symbols !@#$%^&*()';
        $_ENV['TEST_NEWLINE'] = "value\nwith\nnewlines";
        $_ENV['TEST_QUOTES'] = 'value "with" quotes';
        
        $source = new EnvironmentSource('TEST_');
        
        $this->assertEquals('value with spaces and symbols !@#$%^&*()', $source->get('SPECIAL'));
        $this->assertEquals("value\nwith\nnewlines", $source->get('NEWLINE'));
        $this->assertEquals('value "with" quotes', $source->get('QUOTES'));
    }

    public function testLongPrefix(): void
    {
        $_ENV['VERY_LONG_PREFIX_NAME_FOR_TESTING_VAR'] = 'value';
        
        $source = new EnvironmentSource('VERY_LONG_PREFIX_NAME_FOR_TESTING_');
        
        $this->assertTrue($source->has('VAR'));
        $this->assertEquals('value', $source->get('VAR'));
    }

    public function testEmptyPrefixMatchesAll(): void
    {
        $_ENV['APP_VAR'] = 'app_value';
        $_ENV['SYSTEM_VAR'] = 'system_value';
        $_ENV['RANDOM_VAR'] = 'random_value';
        
        $source = new EnvironmentSource('');
        $data = $source->load();
        
        $this->assertArrayHasKey('APP_VAR', $data);
        $this->assertArrayHasKey('SYSTEM_VAR', $data);
        $this->assertArrayHasKey('RANDOM_VAR', $data);
        
        $this->assertEquals('app_value', $data['APP_VAR']);
        $this->assertEquals('system_value', $data['SYSTEM_VAR']);
        $this->assertEquals('random_value', $data['RANDOM_VAR']);
    }

    public function testPrefixCaseSensitivity(): void
    {
        $_ENV['TEST_VAR'] = 'uppercase_prefix';
        $_ENV['test_var'] = 'lowercase_prefix';
        
        $upperSource = new EnvironmentSource('TEST_');
        $lowerSource = new EnvironmentSource('test_');
        
        $this->assertTrue($upperSource->has('VAR'));
        $this->assertEquals('uppercase_prefix', $upperSource->get('VAR'));
        
        $this->assertTrue($lowerSource->has('var'));
        $this->assertEquals('lowercase_prefix', $lowerSource->get('var'));
    }

    public function testPriorityImmutable(): void
    {
        $_ENV['TEST_VAR'] = 'value';
        
        $source = new EnvironmentSource('TEST_', 75);
        $this->assertEquals(75, $source->getPriority());
        
        // Priority should remain the same after operations
        $source->load();
        $this->assertEquals(75, $source->getPriority());
        
        $source->clearCache();
        $this->assertEquals(75, $source->getPriority());
        
        $source->setPrefix('NEW_');
        $this->assertEquals(75, $source->getPriority());
    }
} 