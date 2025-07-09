<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Source;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Source\JsonFileSource;
use dzentota\ConfigLoader\Exception\SourceException;

class JsonFileSourceTest extends TestCase
{
    private string $testFile;
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/config_loader_tests_' . uniqid();
        mkdir($this->testDir, 0755, true);
        $this->testFile = $this->testDir . '/test_config.json';
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
        
        parent::tearDown();
    }

    public function testConstructor(): void
    {
        $source = new JsonFileSource($this->testFile, 50, true);
        
        $this->assertEquals(50, $source->getPriority());
        $this->assertEquals($this->testFile, $source->getFilePath());
        $this->assertTrue($source->isRequired());
        $this->assertEquals('JSON File (' . $this->testFile . ')', $source->getName());
    }

    public function testConstructorWithDefaults(): void
    {
        $source = new JsonFileSource($this->testFile);
        
        $this->assertEquals(50, $source->getPriority()); // Default priority
        $this->assertTrue($source->isRequired()); // Default required
        $this->assertEquals($this->testFile, $source->getFilePath());
    }

    public function testConstructorOptionalFile(): void
    {
        $source = new JsonFileSource($this->testFile, 30, false);
        
        $this->assertEquals(30, $source->getPriority());
        $this->assertFalse($source->isRequired());
        $this->assertEquals($this->testFile, $source->getFilePath());
    }

    public function testLoadSimpleJson(): void
    {
        $data = [
            'port' => '8080',
            'debug' => 'true',
            'host' => 'localhost'
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $this->assertIsArray($loaded);
        $this->assertEquals($data, $loaded);
    }

    public function testLoadNestedJson(): void
    {
        $data = [
            'database' => [
                'host' => 'localhost',
                'port' => '5432',
                'credentials' => [
                    'username' => 'admin',
                    'password' => 'secret'
                ]
            ],
            'server' => [
                'port' => '8080'
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $expected = [
            'database.host' => 'localhost',
            'database.port' => '5432',
            'database.credentials.username' => 'admin',
            'database.credentials.password' => 'secret',
            'server.port' => '8080'
        ];
        
        $this->assertEquals($expected, $loaded);
    }

    public function testLoadWithArrayValues(): void
    {
        $data = [
            'servers' => ['server1', 'server2', 'server3'],
            'config' => [
                'allowed_ips' => ['192.168.1.1', '192.168.1.2'],
                'settings' => [
                    'debug' => 'true'
                ]
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $expected = [
            'servers' => ['server1', 'server2', 'server3'],
            'config.allowed_ips' => ['192.168.1.1', '192.168.1.2'],
            'config.settings.debug' => 'true'
        ];
        
        $this->assertEquals($expected, $loaded);
    }

    public function testLoadEmptyJson(): void
    {
        file_put_contents($this->testFile, '{}');
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    public function testLoadEmptyFile(): void
    {
        file_put_contents($this->testFile, '');
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    public function testLoadCaching(): void
    {
        $data = ['port' => '8080'];
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        // First load
        $loaded1 = $source->load();
        $this->assertEquals($data, $loaded1);
        
        // Change file content
        $newData = ['port' => '9090'];
        file_put_contents($this->testFile, json_encode($newData));
        
        // Second load (should return cached data)
        $loaded2 = $source->load();
        $this->assertEquals($data, $loaded2); // Should still be cached
        
        // Clear cache and load again
        $source->clearCache();
        $loaded3 = $source->load();
        $this->assertEquals($newData, $loaded3); // Should now see new data
    }

    public function testLoadRequiredFileNotFound(): void
    {
        $source = new JsonFileSource('/non/existent/file.json', 50, true);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Required JSON configuration file does not exist');
        
        $source->load();
    }

    public function testLoadOptionalFileNotFound(): void
    {
        $source = new JsonFileSource('/non/existent/file.json', 50, false);
        
        $loaded = $source->load();
        
        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    public function testLoadUnreadableFile(): void
    {
        file_put_contents($this->testFile, '{"port": "8080"}');
        chmod($this->testFile, 0000); // Make file unreadable
        
        $source = new JsonFileSource($this->testFile);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('JSON configuration file is not readable');
        
        try {
            $source->load();
        } finally {
            chmod($this->testFile, 0644); // Restore permissions for cleanup
        }
    }

    public function testLoadInvalidJson(): void
    {
        file_put_contents($this->testFile, '{"invalid": json}');
        
        $source = new JsonFileSource($this->testFile);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Invalid JSON in configuration file');
        
        $source->load();
    }

    public function testLoadNonArrayJson(): void
    {
        file_put_contents($this->testFile, '"string_value"');
        
        $source = new JsonFileSource($this->testFile);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('JSON configuration file must contain an object/array at root level');
        
        $source->load();
    }

    public function testLoadNumericJson(): void
    {
        file_put_contents($this->testFile, '42');
        
        $source = new JsonFileSource($this->testFile);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('JSON configuration file must contain an object/array at root level');
        
        $source->load();
    }

    public function testHas(): void
    {
        $data = [
            'port' => '8080',
            'database' => [
                'host' => 'localhost'
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        $this->assertTrue($source->has('port'));
        $this->assertTrue($source->has('database.host'));
        $this->assertFalse($source->has('non_existent'));
        $this->assertFalse($source->has('database.port'));
    }

    public function testGet(): void
    {
        $data = [
            'port' => '8080',
            'debug' => true,
            'database' => [
                'host' => 'localhost',
                'port' => 5432
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        $this->assertEquals('8080', $source->get('port'));
        $this->assertTrue($source->get('debug'));
        $this->assertEquals('localhost', $source->get('database.host'));
        $this->assertEquals(5432, $source->get('database.port'));
    }

    public function testGetThrowsExceptionForNonExistentKey(): void
    {
        $data = ['port' => '8080'];
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Configuration key "non_existent" not found in JSON file');
        
        $source->get('non_existent');
    }

    public function testGetSourceExceptionDetails(): void
    {
        $data = ['port' => '8080'];
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        try {
            $source->get('missing_key');
            $this->fail('Expected SourceException was not thrown');
        } catch (SourceException $e) {
            $this->assertEquals($this->testFile, $e->getSourceName());
            $this->assertEquals('json_file', $e->getSourceType());
            $this->assertStringContainsString('Configuration key "missing_key" not found in JSON file', $e->getMessage());
        }
    }

    public function testGetName(): void
    {
        $source = new JsonFileSource($this->testFile);
        $this->assertEquals('JSON File (' . $this->testFile . ')', $source->getName());
    }

    public function testClearCache(): void
    {
        $data = ['port' => '8080'];
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        // Load and verify initial value
        $loaded = $source->load();
        $this->assertEquals('8080', $loaded['port']);
        
        // Change file content
        $newData = ['port' => '9090'];
        file_put_contents($this->testFile, json_encode($newData));
        
        // Should still return cached value
        $loaded = $source->load();
        $this->assertEquals('8080', $loaded['port']);
        
        // Clear cache
        $source->clearCache();
        
        // Should now return updated value
        $loaded = $source->load();
        $this->assertEquals('9090', $loaded['port']);
    }

    public function testGetFilePath(): void
    {
        $source = new JsonFileSource($this->testFile);
        $this->assertEquals($this->testFile, $source->getFilePath());
    }

    public function testIsRequired(): void
    {
        $requiredSource = new JsonFileSource($this->testFile, 50, true);
        $this->assertTrue($requiredSource->isRequired());
        
        $optionalSource = new JsonFileSource($this->testFile, 50, false);
        $this->assertFalse($optionalSource->isRequired());
    }

    public function testFileExists(): void
    {
        $source = new JsonFileSource($this->testFile);
        $this->assertFalse($source->fileExists());
        
        file_put_contents($this->testFile, '{}');
        $this->assertTrue($source->fileExists());
    }

    public function testGetFileModificationTime(): void
    {
        $source = new JsonFileSource($this->testFile);
        $this->assertEquals(0, $source->getFileModificationTime());
        
        file_put_contents($this->testFile, '{}');
        $mtime = $source->getFileModificationTime();
        $this->assertGreaterThan(0, $mtime);
        $this->assertLessThanOrEqual(time(), $mtime);
    }

    public function testComplexNestedStructure(): void
    {
        $data = [
            'app' => [
                'name' => 'TestApp',
                'version' => '1.0.0',
                'features' => [
                    'auth' => [
                        'enabled' => true,
                        'providers' => [
                            'oauth' => [
                                'google' => [
                                    'client_id' => 'google_client_id',
                                    'client_secret' => 'google_secret'
                                ],
                                'github' => [
                                    'client_id' => 'github_client_id'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $this->assertTrue($source->has('app.name'));
        $this->assertTrue($source->has('app.features.auth.enabled'));
        $this->assertTrue($source->has('app.features.auth.providers.oauth.google.client_id'));
        
        $this->assertEquals('TestApp', $source->get('app.name'));
        $this->assertTrue($source->get('app.features.auth.enabled'));
        $this->assertEquals('google_client_id', $source->get('app.features.auth.providers.oauth.google.client_id'));
    }

    public function testMixedDataTypes(): void
    {
        $data = [
            'string' => 'text',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'array' => ['item1', 'item2'],
            'nested' => [
                'mixed' => [
                    'number' => 100,
                    'text' => 'nested_text'
                ]
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        $this->assertEquals('text', $source->get('string'));
        $this->assertEquals(42, $source->get('integer'));
        $this->assertEquals(3.14, $source->get('float'));
        $this->assertTrue($source->get('boolean_true'));
        $this->assertFalse($source->get('boolean_false'));
        $this->assertEquals(['item1', 'item2'], $source->get('array'));
        $this->assertEquals(100, $source->get('nested.mixed.number'));
        $this->assertEquals('nested_text', $source->get('nested.mixed.text'));
    }

    public function testUnicodeCharacters(): void
    {
        $data = [
            'unicode' => 'Héllo Wörld 🌍',
            'chinese' => '你好世界',
            'emoji' => '👋🌟💫'
        ];
        
        file_put_contents($this->testFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        
        $source = new JsonFileSource($this->testFile);
        
        $this->assertEquals('Héllo Wörld 🌍', $source->get('unicode'));
        $this->assertEquals('你好世界', $source->get('chinese'));
        $this->assertEquals('👋🌟💫', $source->get('emoji'));
    }

    public function testSpecialCharactersInKeys(): void
    {
        $data = [
            'key-with-dashes' => 'value1',
            'key_with_underscores' => 'value2',
            'key.with.dots' => 'value3', // This will create nested structure
            'nested' => [
                'key-with-dashes' => 'nested_value'
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        
        $this->assertEquals('value1', $source->get('key-with-dashes'));
        $this->assertEquals('value2', $source->get('key_with_underscores'));
        $this->assertEquals('value3', $source->get('key.with.dots'));
        $this->assertEquals('nested_value', $source->get('nested.key-with-dashes'));
    }

    public function testEmptyNestedObjects(): void
    {
        $data = [
            'empty_object' => [],
            'nested' => [
                'empty_nested' => []
            ]
        ];
        
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile);
        $loaded = $source->load();
        
        $this->assertEquals([], $loaded['empty_object']);
        $this->assertEquals([], $loaded['nested.empty_nested']);
    }

    public function testPriorityImmutable(): void
    {
        $data = ['port' => '8080'];
        file_put_contents($this->testFile, json_encode($data));
        
        $source = new JsonFileSource($this->testFile, 75);
        $this->assertEquals(75, $source->getPriority());
        
        // Priority should remain the same after operations
        $source->load();
        $this->assertEquals(75, $source->getPriority());
        
        $source->clearCache();
        $this->assertEquals(75, $source->getPriority());
    }

    public function testFilePathImmutable(): void
    {
        $source = new JsonFileSource($this->testFile);
        $this->assertEquals($this->testFile, $source->getFilePath());
        
        // File path should remain the same after operations
        if (file_exists($this->testFile)) {
            $data = ['port' => '8080'];
            file_put_contents($this->testFile, json_encode($data));
            $source->load();
        }
        
        $this->assertEquals($this->testFile, $source->getFilePath());
    }
} 