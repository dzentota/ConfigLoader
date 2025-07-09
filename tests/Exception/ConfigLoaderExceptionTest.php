<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Exception;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Exception\ConfigLoaderException;

class ConfigLoaderExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new ConfigLoaderException('Test message');
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithCode(): void
    {
        $exception = new ConfigLoaderException('Test message', 123);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPrevious(): void
    {
        $previousException = new \Exception('Previous exception');
        $exception = new ConfigLoaderException('Test message', 0, $previousException);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testConstructorWithAllParameters(): void
    {
        $previousException = new \Exception('Previous exception');
        $exception = new ConfigLoaderException('Test message', 456, $previousException);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(456, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testInheritsFromException(): void
    {
        $exception = new ConfigLoaderException('Test message');
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testCanBeThrown(): void
    {
        $this->expectException(ConfigLoaderException::class);
        $this->expectExceptionMessage('Test message');
        
        throw new ConfigLoaderException('Test message');
    }

    public function testCanBeCaught(): void
    {
        $caught = false;
        
        try {
            throw new ConfigLoaderException('Test message');
        } catch (ConfigLoaderException $e) {
            $caught = true;
            $this->assertEquals('Test message', $e->getMessage());
        }
        
        $this->assertTrue($caught, 'Exception should have been caught');
    }

    public function testGetTraceAsString(): void
    {
        $exception = new ConfigLoaderException('Test message');
        
        $trace = $exception->getTraceAsString();
        
        $this->assertIsString($trace);
        $this->assertStringContainsString(__CLASS__, $trace);
        $this->assertStringContainsString(__FUNCTION__, $trace);
    }

    public function testGetFile(): void
    {
        $exception = new ConfigLoaderException('Test message');
        
        $this->assertEquals(__FILE__, $exception->getFile());
    }

    public function testGetLine(): void
    {
        $line = __LINE__ + 1;
        $exception = new ConfigLoaderException('Test message');
        
        $this->assertEquals($line, $exception->getLine());
    }

    public function testToString(): void
    {
        $exception = new ConfigLoaderException('Test message');
        
        $string = (string) $exception;
        
        $this->assertIsString($string);
        $this->assertStringContainsString('ConfigLoaderException', $string);
        $this->assertStringContainsString('Test message', $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testSerializable(): void
    {
        $exception = new ConfigLoaderException('Test message', 123);
        
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(ConfigLoaderException::class, $unserialized);
        $this->assertEquals('Test message', $unserialized->getMessage());
        $this->assertEquals(123, $unserialized->getCode());
    }
} 