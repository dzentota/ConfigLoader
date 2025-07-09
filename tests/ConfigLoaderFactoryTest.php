<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\ConfigLoaderFactory;
use dzentota\ConfigLoader\ConfigLoader;
use dzentota\ConfigLoader\Source\EnvironmentSource;
use dzentota\ConfigLoader\Source\JsonFileSource;
use dzentota\ConfigLoader\Source\ArraySource;
use DomainPrimitives\Network\Port;
use DomainPrimitives\Configuration\FeatureFlag;

class ConfigLoaderFactoryTest extends TestCase
{
    private string $testJsonFile;
    private string $testMultipleJsonFile1;
    private string $testMultipleJsonFile2;
    private string $testSecretsDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary test files
        $this->testJsonFile = tempnam(sys_get_temp_dir(), 'config_test_');
        $this->testMultipleJsonFile1 = tempnam(sys_get_temp_dir(), 'config_test_1_');
        $this->testMultipleJsonFile2 = tempnam(sys_get_temp_dir(), 'config_test_2_');
        $this->testSecretsDir = sys_get_temp_dir() . '/test_secrets_' . uniqid();
        
        // Create test JSON files
        file_put_contents($this->testJsonFile, json_encode([
            'port' => '8080',
            'debug' => 'true',
            'database' => [
                'host' => 'localhost',
                'port' => '5432'
            ]
        ]));
        
        file_put_contents($this->testMultipleJsonFile1, json_encode([
            'port' => '3000',
            'env' => 'development'
        ]));
        
        file_put_contents($this->testMultipleJsonFile2, json_encode([
            'port' => '4000',
            'feature_x' => 'enabled'
        ]));
        
        // Create test secrets directory
        mkdir($this->testSecretsDir);
        file_put_contents($this->testSecretsDir . '/db_password', 'secret123');
        file_put_contents($this->testSecretsDir . '/api_key', 'key456');
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testJsonFile)) {
            unlink($this->testJsonFile);
        }
        if (file_exists($this->testMultipleJsonFile1)) {
            unlink($this->testMultipleJsonFile1);
        }
        if (file_exists($this->testMultipleJsonFile2)) {
            unlink($this->testMultipleJsonFile2);
        }
        
        // Clean up secrets directory
        if (is_dir($this->testSecretsDir)) {
            $files = glob($this->testSecretsDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testSecretsDir);
        }
        
        parent::tearDown();
    }

    public function testCreateFromEnvironment(): void
    {
        $_ENV['TEST_PORT'] = '8080';
        $_ENV['TEST_DEBUG'] = 'true';
        
        $loader = ConfigLoaderFactory::createFromEnvironment('TEST_');
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertTrue($loader->isStrict());
        $this->assertTrue($loader->has('PORT'));
        $this->assertTrue($loader->has('DEBUG'));
        
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(8080, $port->toInt());
        
        $debug = $loader->get('DEBUG', FeatureFlag::class);
        $this->assertTrue($debug->isEnabled());
        
        unset($_ENV['TEST_PORT'], $_ENV['TEST_DEBUG']);
    }

    public function testCreateFromEnvironmentWithoutPrefix(): void
    {
        $_ENV['PORT'] = '9090';
        
        $loader = ConfigLoaderFactory::createFromEnvironment('', false);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertFalse($loader->isStrict());
        $this->assertTrue($loader->has('PORT'));
        
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(9090, $port->toInt());
        
        unset($_ENV['PORT']);
    }

    public function testCreateFromJsonAndEnv(): void
    {
        $_ENV['TEST_PORT'] = '9000'; // Should override JSON value
        
        $loader = ConfigLoaderFactory::createFromJsonAndEnv($this->testJsonFile, 'TEST_');
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertTrue($loader->isStrict());
        
        // Environment should override JSON
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(9000, $port->toInt());
        
        // JSON values should be available
        $this->assertTrue($loader->has('database.host'));
        $this->assertEquals('localhost', $loader->getRaw('database.host'));
        
        unset($_ENV['TEST_PORT']);
    }

    public function testCreateFromJsonAndEnvWithMissingFile(): void
    {
        // The exception should be thrown when trying to access a configuration value
        $loader = ConfigLoaderFactory::createFromJsonAndEnv('/non/existent/file.json', 'TEST_');
        
        $this->expectException(\dzentota\ConfigLoader\Exception\SourceException::class);
        $loader->getRaw('anykey'); // This should trigger the exception when loading sources
    }

    public function testCreateFromJsonAndEnvWithOptionalFile(): void
    {
        $_ENV['TEST_PORT'] = '8080';
        
        $loader = ConfigLoaderFactory::createFromJsonAndEnv('/non/existent/file.json', 'TEST_', true, false);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertTrue($loader->has('PORT'));
        
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(8080, $port->toInt());
        
        unset($_ENV['TEST_PORT']);
    }

    public function testCreateFromMultipleFiles(): void
    {
        $_ENV['TEST_FEATURE_X'] = 'disabled'; // Should override JSON value
        $_ENV['TEST_PORT'] = '5000'; // Should override JSON files
        
        $loader = ConfigLoaderFactory::createFromMultipleFiles(
            [$this->testMultipleJsonFile1, $this->testMultipleJsonFile2],
            'TEST_'
        );
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        
        // Environment should override JSON files
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(5000, $port->toInt()); // From environment
        
        // First file value should be available
        $this->assertEquals('development', $loader->getRaw('env'));
        
        // Environment should override JSON
        $this->assertEquals('disabled', $loader->getRaw('FEATURE_X'));
        
        unset($_ENV['TEST_FEATURE_X'], $_ENV['TEST_PORT']);
    }

    public function testCreateFromArrayAndEnv(): void
    {
        $_ENV['TEST_PORT'] = '7000'; // Should override array value
        
        $configData = [
            'port' => '6000',
            'database.host' => 'localhost',
            'debug' => 'false'
        ];
        
        $loader = ConfigLoaderFactory::createFromArrayAndEnv($configData, 'TEST_');
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        
        // Environment should override array
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(7000, $port->toInt());
        
        // Array values should be available
        $this->assertEquals('localhost', $loader->getRaw('database.host'));
        
        unset($_ENV['TEST_PORT']);
    }

    public function testCreateLayered(): void
    {
        $_ENV['TEST_DEBUG'] = 'true'; // Should override JSON and defaults
        $_ENV['TEST_PORT'] = '9999'; // Should override JSON and defaults
        
        $defaults = [
            'port' => '3000',
            'debug' => 'false',
            'timeout' => '30'
        ];
        
        $loader = ConfigLoaderFactory::createLayered($defaults, $this->testJsonFile, 'TEST_');
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        
        // Environment should have highest priority
        $debug = $loader->get('DEBUG', FeatureFlag::class);
        $this->assertTrue($debug->isEnabled());
        
        // Environment should override JSON and defaults
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(9999, $port->toInt()); // From environment
        
        // Default should be available when not overridden
        $this->assertEquals('30', $loader->getRaw('timeout'));
        
        unset($_ENV['TEST_DEBUG'], $_ENV['TEST_PORT']);
    }

    public function testCreateLayeredWithOptionalFile(): void
    {
        $_ENV['TEST_PORT'] = '3000'; // Set environment variable to ensure PORT is available
        
        $defaults = [
            'port' => '3000',
            'debug' => 'false'
        ];
        
        $loader = ConfigLoaderFactory::createLayered($defaults, '/non/existent/file.json', 'TEST_', true, false);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        
        // Should use environment variable (highest priority)
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(3000, $port->toInt());
        
        unset($_ENV['TEST_PORT']);
    }

    public function testCreateTwelveFactor(): void
    {
        $_ENV['MYAPP_PORT'] = '12000';
        $_ENV['MYAPP_DEBUG'] = 'true';
        
        $loader = ConfigLoaderFactory::createTwelveFactor('myapp');
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertTrue($loader->isStrict());
        
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(12000, $port->toInt());
        
        $debug = $loader->get('DEBUG', FeatureFlag::class);
        $this->assertTrue($debug->isEnabled());
        
        unset($_ENV['MYAPP_PORT'], $_ENV['MYAPP_DEBUG']);
    }

    public function testCreateTwelveFactorNonStrict(): void
    {
        $loader = ConfigLoaderFactory::createTwelveFactor('myapp', false);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertFalse($loader->isStrict());
    }

    public function testCreateForDocker(): void
    {
        $_ENV['DOCKER_PORT'] = '8080';
        
        $loader = ConfigLoaderFactory::createForDocker('DOCKER_', $this->testSecretsDir);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        
        // Environment variables should be available
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(8080, $port->toInt());
        
        // Secrets should be available
        $this->assertTrue($loader->has('db_password'));
        $this->assertEquals('secret123', $loader->getRaw('db_password'));
        
        $this->assertTrue($loader->has('api_key'));
        $this->assertEquals('key456', $loader->getRaw('api_key'));
        
        unset($_ENV['DOCKER_PORT']);
    }

    public function testCreateForDockerWithNonExistentSecretsDir(): void
    {
        $_ENV['DOCKER_PORT'] = '8080';
        
        $loader = ConfigLoaderFactory::createForDocker('DOCKER_', '/non/existent/secrets');
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        
        // Should still work with environment variables
        $port = $loader->get('PORT', Port::class);
        $this->assertEquals(8080, $port->toInt());
        
        unset($_ENV['DOCKER_PORT']);
    }

    public function testCreateForTesting(): void
    {
        $testData = [
            'port' => '8080',
            'debug' => 'true',
            'database.host' => 'localhost'
        ];
        
        $loader = ConfigLoaderFactory::createForTesting($testData);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertTrue($loader->isStrict()); // Testing mode is always strict
        
        $port = $loader->get('port', Port::class);
        $this->assertEquals(8080, $port->toInt());
        
        $debug = $loader->get('debug', FeatureFlag::class);
        $this->assertTrue($debug->isEnabled());
        
        $this->assertEquals('localhost', $loader->getRaw('database.host'));
    }

    public function testCreateForTestingWithEmptyData(): void
    {
        $loader = ConfigLoaderFactory::createForTesting([]);
        
        $this->assertInstanceOf(ConfigLoader::class, $loader);
        $this->assertTrue($loader->isStrict());
        
        // In strict mode, should throw exception for missing keys without default
        $this->expectException(\dzentota\ConfigLoader\Exception\ConfigLoaderException::class);
        $loader->get('port', Port::class);
    }
} 