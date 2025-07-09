<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Exception;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Exception\SourceException;
use dzentota\ConfigLoader\Exception\ConfigLoaderException;

class SourceExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new SourceException('config.json', 'json_file', 'File not found');
        
        $this->assertEquals('config.json', $exception->getSourceName());
        $this->assertEquals('json_file', $exception->getSourceType());
        $this->assertStringContainsString('Failed to load from json_file source "config.json"', $exception->getMessage());
        $this->assertStringContainsString('File not found', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPrevious(): void
    {
        $previousException = new \Exception('Previous exception');
        $exception = new SourceException('config.json', 'json_file', 'File not found', $previousException);
        
        $this->assertEquals('config.json', $exception->getSourceName());
        $this->assertEquals('json_file', $exception->getSourceType());
        $this->assertStringContainsString('File not found', $exception->getMessage());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testInheritsFromConfigLoaderException(): void
    {
        $exception = new SourceException('source', 'type', 'message');
        
        $this->assertInstanceOf(ConfigLoaderException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testGetSourceName(): void
    {
        $exception = new SourceException('/path/to/config.json', 'json_file', 'File not readable');
        
        $this->assertEquals('/path/to/config.json', $exception->getSourceName());
    }

    public function testGetSourceType(): void
    {
        $exception = new SourceException('ENV_VAR', 'environment', 'Environment variable not set');
        
        $this->assertEquals('environment', $exception->getSourceType());
    }

    public function testCompleteMessageFormat(): void
    {
        $exception = new SourceException('config.json', 'json_file', 'File does not exist');
        
        $expectedMessage = 'Failed to load from json_file source "config.json": File does not exist';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testEnvironmentSourceException(): void
    {
        $exception = new SourceException('APP_DATABASE_URL', 'environment', 'Environment variable not found');
        
        $this->assertEquals('APP_DATABASE_URL', $exception->getSourceName());
        $this->assertEquals('environment', $exception->getSourceType());
        $this->assertStringContainsString('Failed to load from environment source "APP_DATABASE_URL"', $exception->getMessage());
        $this->assertStringContainsString('Environment variable not found', $exception->getMessage());
    }

    public function testArraySourceException(): void
    {
        $exception = new SourceException('missing_key', 'array', 'Configuration key not found in array source');
        
        $this->assertEquals('missing_key', $exception->getSourceName());
        $this->assertEquals('array', $exception->getSourceType());
        $this->assertStringContainsString('Failed to load from array source "missing_key"', $exception->getMessage());
        $this->assertStringContainsString('Configuration key not found in array source', $exception->getMessage());
    }

    public function testJsonFileSourceException(): void
    {
        $exception = new SourceException('/etc/app/config.json', 'json_file', 'Invalid JSON syntax');
        
        $this->assertEquals('/etc/app/config.json', $exception->getSourceName());
        $this->assertEquals('json_file', $exception->getSourceType());
        $this->assertStringContainsString('Failed to load from json_file source "/etc/app/config.json"', $exception->getMessage());
        $this->assertStringContainsString('Invalid JSON syntax', $exception->getMessage());
    }

    public function testCanBeThrown(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Failed to load from json_file source "config.json"');
        
        throw new SourceException('config.json', 'json_file', 'File not found');
    }

    public function testCanBeCaught(): void
    {
        $caught = false;
        
        try {
            throw new SourceException('config.json', 'json_file', 'File not found');
        } catch (SourceException $e) {
            $caught = true;
            $this->assertEquals('config.json', $e->getSourceName());
            $this->assertEquals('json_file', $e->getSourceType());
        }
        
        $this->assertTrue($caught, 'SourceException should have been caught');
    }

    public function testCanBeCaughtAsConfigLoaderException(): void
    {
        $caught = false;
        
        try {
            throw new SourceException('config.json', 'json_file', 'File not found');
        } catch (ConfigLoaderException $e) {
            $caught = true;
            $this->assertInstanceOf(SourceException::class, $e);
        }
        
        $this->assertTrue($caught, 'SourceException should have been caught as ConfigLoaderException');
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('Root cause');
        $previous = new \Exception('Previous exception', 0, $rootCause);
        $exception = new SourceException('config.json', 'json_file', 'File not found', $previous);
        
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($rootCause, $exception->getPrevious()->getPrevious());
    }

    public function testWithEmptySourceName(): void
    {
        $exception = new SourceException('', 'json_file', 'Invalid source name');
        
        $this->assertEquals('', $exception->getSourceName());
        $this->assertStringContainsString('json_file source ""', $exception->getMessage());
    }

    public function testWithEmptySourceType(): void
    {
        $exception = new SourceException('config.json', '', 'Invalid source type');
        
        $this->assertEquals('', $exception->getSourceType());
        $this->assertStringContainsString('Failed to load from  source "config.json"', $exception->getMessage());
    }

    public function testWithEmptyMessage(): void
    {
        $exception = new SourceException('config.json', 'json_file', '');
        
        $this->assertEquals('config.json', $exception->getSourceName());
        $this->assertEquals('json_file', $exception->getSourceType());
        $this->assertStringContainsString('Failed to load from json_file source "config.json": ', $exception->getMessage());
    }

    public function testWithComplexSourceName(): void
    {
        $exception = new SourceException('/var/app/config/database/connection.json', 'json_file', 'Permission denied');
        
        $this->assertEquals('/var/app/config/database/connection.json', $exception->getSourceName());
        $this->assertStringContainsString('source "/var/app/config/database/connection.json"', $exception->getMessage());
    }

    public function testWithLongMessage(): void
    {
        $longMessage = str_repeat('Error: ', 100) . 'File processing failed';
        $exception = new SourceException('config.json', 'json_file', $longMessage);
        
        $this->assertStringContainsString($longMessage, $exception->getMessage());
    }

    public function testWithSpecialCharactersInSourceName(): void
    {
        $exception1 = new SourceException('config with spaces.json', 'json_file', 'Invalid filename');
        $this->assertStringContainsString('source "config with spaces.json"', $exception1->getMessage());
        
        $exception2 = new SourceException('config"with"quotes.json', 'json_file', 'Invalid filename');
        $this->assertStringContainsString('source "config"with"quotes.json"', $exception2->getMessage());
        
        $exception3 = new SourceException('config-with-unicode-🌍.json', 'json_file', 'Invalid filename');
        $this->assertStringContainsString('source "config-with-unicode-🌍.json"', $exception3->getMessage());
    }

    public function testWithSpecialCharactersInSourceType(): void
    {
        $exception1 = new SourceException('config.json', 'json-file', 'Invalid type');
        $this->assertStringContainsString('from json-file source', $exception1->getMessage());
        
        $exception2 = new SourceException('config.json', 'json_file_v2', 'Invalid type');
        $this->assertStringContainsString('from json_file_v2 source', $exception2->getMessage());
    }

    public function testWithSpecialCharactersInMessage(): void
    {
        $exception1 = new SourceException('config.json', 'json_file', 'Error: "File not found"');
        $this->assertStringContainsString('Error: "File not found"', $exception1->getMessage());
        
        $exception2 = new SourceException('config.json', 'json_file', "Error:\nMultiline\nMessage");
        $this->assertStringContainsString("Error:\nMultiline\nMessage", $exception2->getMessage());
        
        $exception3 = new SourceException('config.json', 'json_file', 'Error with unicode: 🚨');
        $this->assertStringContainsString('Error with unicode: 🚨', $exception3->getMessage());
    }

    public function testSerializable(): void
    {
        $exception = new SourceException('config.json', 'json_file', 'File not found');
        
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(SourceException::class, $unserialized);
        $this->assertEquals('config.json', $unserialized->getSourceName());
        $this->assertEquals('json_file', $unserialized->getSourceType());
        $this->assertEquals($exception->getMessage(), $unserialized->getMessage());
    }

    public function testToString(): void
    {
        $exception = new SourceException('config.json', 'json_file', 'File not found');
        
        $string = (string) $exception;
        
        $this->assertIsString($string);
        $this->assertStringContainsString('SourceException', $string);
        $this->assertStringContainsString('Failed to load from json_file source "config.json"', $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testDifferentSourceTypes(): void
    {
        $sourceTypes = [
            'environment' => 'Environment variable',
            'json_file' => 'JSON file',
            'array' => 'Array source',
            'xml_file' => 'XML file',
            'yaml_file' => 'YAML file',
            'database' => 'Database source',
            'redis' => 'Redis source',
            'custom' => 'Custom source'
        ];
        
        foreach ($sourceTypes as $type => $description) {
            $exception = new SourceException('source_name', $type, 'Test error');
            
            $this->assertEquals($type, $exception->getSourceType());
            $this->assertStringContainsString("from $type source", $exception->getMessage());
        }
    }

    public function testImmutableProperties(): void
    {
        $exception = new SourceException('config.json', 'json_file', 'File not found');
        
        $sourceName = $exception->getSourceName();
        $sourceType = $exception->getSourceType();
        
        // Properties should remain the same
        $this->assertEquals($sourceName, $exception->getSourceName());
        $this->assertEquals($sourceType, $exception->getSourceType());
    }

    public function testWithFileSystemErrors(): void
    {
        $exception = new SourceException('/protected/config.json', 'json_file', 'Permission denied');
        
        $this->assertEquals('/protected/config.json', $exception->getSourceName());
        $this->assertEquals('json_file', $exception->getSourceType());
        $this->assertStringContainsString('Permission denied', $exception->getMessage());
    }

    public function testWithNetworkErrors(): void
    {
        $exception = new SourceException('https://api.example.com/config', 'remote_json', 'Connection timeout');
        
        $this->assertEquals('https://api.example.com/config', $exception->getSourceName());
        $this->assertEquals('remote_json', $exception->getSourceType());
        $this->assertStringContainsString('Connection timeout', $exception->getMessage());
    }

    public function testWithDatabaseErrors(): void
    {
        $exception = new SourceException('config_table', 'database', 'Table does not exist');
        
        $this->assertEquals('config_table', $exception->getSourceName());
        $this->assertEquals('database', $exception->getSourceType());
        $this->assertStringContainsString('Table does not exist', $exception->getMessage());
    }
} 