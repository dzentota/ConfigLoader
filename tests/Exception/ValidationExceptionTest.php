<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Exception;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Exception\ValidationException;
use dzentota\ConfigLoader\Exception\ConfigLoaderException;

class ValidationExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new ValidationException('port', '8080', 'Invalid port value');
        
        $this->assertEquals('port', $exception->getConfigKey());
        $this->assertEquals('8080', $exception->getRawValue());
        $this->assertStringContainsString('Validation failed for config key "port"', $exception->getMessage());
        $this->assertStringContainsString('with value "8080"', $exception->getMessage());
        $this->assertStringContainsString('Invalid port value', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPrevious(): void
    {
        $previousException = new \Exception('Previous exception');
        $exception = new ValidationException('port', '8080', 'Invalid port value', $previousException);
        
        $this->assertEquals('port', $exception->getConfigKey());
        $this->assertEquals('8080', $exception->getRawValue());
        $this->assertStringContainsString('Invalid port value', $exception->getMessage());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testInheritsFromConfigLoaderException(): void
    {
        $exception = new ValidationException('key', 'value', 'message');
        
        $this->assertInstanceOf(ConfigLoaderException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testGetConfigKey(): void
    {
        $exception = new ValidationException('database.host', 'localhost', 'Invalid host');
        
        $this->assertEquals('database.host', $exception->getConfigKey());
    }

    public function testGetRawValue(): void
    {
        $exception = new ValidationException('port', '8080', 'Invalid port');
        
        $this->assertEquals('8080', $exception->getRawValue());
    }

    public function testGetRawValueWithDifferentTypes(): void
    {
        // Test with integer
        $exception1 = new ValidationException('port', 8080, 'Invalid port');
        $this->assertEquals(8080, $exception1->getRawValue());
        
        // Test with boolean
        $exception2 = new ValidationException('debug', true, 'Invalid debug value');
        $this->assertTrue($exception2->getRawValue());
        
        // Test with null
        $exception3 = new ValidationException('optional', null, 'Invalid optional value');
        $this->assertNull($exception3->getRawValue());
        
        // Test with array
        $exception4 = new ValidationException('config', ['key' => 'value'], 'Invalid config');
        $this->assertEquals(['key' => 'value'], $exception4->getRawValue());
    }

    public function testMessageFormattingWithScalarValues(): void
    {
        $exception1 = new ValidationException('port', 8080, 'Invalid port');
        $this->assertStringContainsString('with value "8080"', $exception1->getMessage());
        
        $exception2 = new ValidationException('debug', true, 'Invalid debug');
        $this->assertStringContainsString('with value "1"', $exception2->getMessage());
        
        $exception3 = new ValidationException('debug', false, 'Invalid debug');
        $this->assertStringContainsString('with value ""', $exception3->getMessage());
        
        $exception4 = new ValidationException('name', 'test', 'Invalid name');
        $this->assertStringContainsString('with value "test"', $exception4->getMessage());
    }

    public function testMessageFormattingWithNonScalarValues(): void
    {
        $exception1 = new ValidationException('config', ['key' => 'value'], 'Invalid config');
        $this->assertStringContainsString('with value "array"', $exception1->getMessage());
        
        $exception2 = new ValidationException('object', (object)['key' => 'value'], 'Invalid object');
        $this->assertStringContainsString('with value "object"', $exception2->getMessage());
        
        $exception3 = new ValidationException('null', null, 'Invalid null');
        $this->assertStringContainsString('with value "NULL"', $exception3->getMessage());
    }

    public function testCompleteMessageFormat(): void
    {
        $exception = new ValidationException('database.port', '3306', 'Port must be between 1 and 65535');
        
        $expectedMessage = 'Validation failed for config key "database.port" with value "3306": Port must be between 1 and 65535';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCanBeThrown(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed for config key "port"');
        
        throw new ValidationException('port', '8080', 'Invalid port value');
    }

    public function testCanBeCaught(): void
    {
        $caught = false;
        
        try {
            throw new ValidationException('port', '8080', 'Invalid port value');
        } catch (ValidationException $e) {
            $caught = true;
            $this->assertEquals('port', $e->getConfigKey());
            $this->assertEquals('8080', $e->getRawValue());
        }
        
        $this->assertTrue($caught, 'ValidationException should have been caught');
    }

    public function testCanBeCaughtAsConfigLoaderException(): void
    {
        $caught = false;
        
        try {
            throw new ValidationException('port', '8080', 'Invalid port value');
        } catch (ConfigLoaderException $e) {
            $caught = true;
            $this->assertInstanceOf(ValidationException::class, $e);
        }
        
        $this->assertTrue($caught, 'ValidationException should have been caught as ConfigLoaderException');
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('Root cause');
        $previous = new \Exception('Previous exception', 0, $rootCause);
        $exception = new ValidationException('port', '8080', 'Invalid port value', $previous);
        
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($rootCause, $exception->getPrevious()->getPrevious());
    }

    public function testWithEmptyConfigKey(): void
    {
        $exception = new ValidationException('', '8080', 'Invalid value');
        
        $this->assertEquals('', $exception->getConfigKey());
        $this->assertStringContainsString('config key ""', $exception->getMessage());
    }

    public function testWithEmptyMessage(): void
    {
        $exception = new ValidationException('port', '8080', '');
        
        $this->assertEquals('port', $exception->getConfigKey());
        $this->assertEquals('8080', $exception->getRawValue());
        $this->assertStringContainsString('Validation failed for config key "port"', $exception->getMessage());
        $this->assertStringContainsString('with value "8080": ', $exception->getMessage());
    }

    public function testWithComplexConfigKey(): void
    {
        $exception = new ValidationException('database.connection.pool.size', '10', 'Invalid pool size');
        
        $this->assertEquals('database.connection.pool.size', $exception->getConfigKey());
        $this->assertStringContainsString('config key "database.connection.pool.size"', $exception->getMessage());
    }

    public function testWithLongRawValue(): void
    {
        $longValue = str_repeat('a', 1000);
        $exception = new ValidationException('key', $longValue, 'Value too long');
        
        $this->assertEquals($longValue, $exception->getRawValue());
        $this->assertStringContainsString('with value "' . $longValue . '"', $exception->getMessage());
    }

    public function testWithSpecialCharactersInValues(): void
    {
        $exception1 = new ValidationException('key', 'value with "quotes"', 'Invalid value');
        $this->assertStringContainsString('with value "value with "quotes""', $exception1->getMessage());
        
        $exception2 = new ValidationException('key', "value\nwith\nnewlines", 'Invalid value');
        $this->assertStringContainsString("with value \"value\nwith\nnewlines\"", $exception2->getMessage());
        
        $exception3 = new ValidationException('key', 'value with unicode: 🌍', 'Invalid value');
        $this->assertStringContainsString('with value "value with unicode: 🌍"', $exception3->getMessage());
    }

    public function testSerializable(): void
    {
        $exception = new ValidationException('port', '8080', 'Invalid port value');
        
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(ValidationException::class, $unserialized);
        $this->assertEquals('port', $unserialized->getConfigKey());
        $this->assertEquals('8080', $unserialized->getRawValue());
        $this->assertEquals($exception->getMessage(), $unserialized->getMessage());
    }

    public function testToString(): void
    {
        $exception = new ValidationException('port', '8080', 'Invalid port value');
        
        $string = (string) $exception;
        
        $this->assertIsString($string);
        $this->assertStringContainsString('ValidationException', $string);
        $this->assertStringContainsString('Validation failed for config key "port"', $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testWithResourceValue(): void
    {
        $resource = fopen('php://memory', 'r');
        $exception = new ValidationException('file', $resource, 'Invalid file');
        
        $this->assertSame($resource, $exception->getRawValue());
        $this->assertStringContainsString('with value "resource"', $exception->getMessage());
        
        fclose($resource);
    }

    public function testWithCallableValue(): void
    {
        $callable = function() { return 'test'; };
        $exception = new ValidationException('callback', $callable, 'Invalid callback');
        
        $this->assertSame($callable, $exception->getRawValue());
        $this->assertStringContainsString('with value "object"', $exception->getMessage());
    }

    public function testImmutableProperties(): void
    {
        $exception = new ValidationException('port', '8080', 'Invalid port value');
        
        $configKey = $exception->getConfigKey();
        $rawValue = $exception->getRawValue();
        
        // Properties should remain the same
        $this->assertEquals($configKey, $exception->getConfigKey());
        $this->assertEquals($rawValue, $exception->getRawValue());
    }
} 