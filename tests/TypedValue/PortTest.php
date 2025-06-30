<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\TypedValue;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\TypedValue\Port;
use dzentota\TypedValue\ValidationResult;

class PortTest extends TestCase
{
    public function testValidPorts(): void
    {
        $port = Port::fromNative('3000');
        $this->assertEquals(3000, $port->toInt());
        $this->assertFalse($port->isWellKnown());
        $this->assertTrue($port->isRegistered());
        $this->assertFalse($port->isDynamic());

        $port = Port::fromNative('80');
        $this->assertEquals(80, $port->toInt());
        $this->assertTrue($port->isWellKnown());
        $this->assertFalse($port->isRegistered());
        $this->assertFalse($port->isDynamic());

        $port = Port::fromNative('50000');
        $this->assertEquals(50000, $port->toInt());
        $this->assertFalse($port->isWellKnown());
        $this->assertFalse($port->isRegistered());
        $this->assertTrue($port->isDynamic());
    }

    public function testValidationErrors(): void
    {
        $result = Port::validate('invalid');
        $this->assertFalse($result->success());
        $this->assertStringContainsString('Port must be numeric', $result->getErrors()[0]->getMessage());

        $result = Port::validate('0');
        $this->assertFalse($result->success());
        $this->assertStringContainsString('Port must be between', $result->getErrors()[0]->getMessage());

        $result = Port::validate('65536');
        $this->assertFalse($result->success());
        $this->assertStringContainsString('Port must be between', $result->getErrors()[0]->getMessage());
    }

    public function testValidationSuccess(): void
    {
        $result = Port::validate('3000');
        $this->assertTrue($result->success());
        $this->assertEmpty($result->getErrors());

        $result = Port::validate(3000);
        $this->assertTrue($result->success());
        $this->assertEmpty($result->getErrors());
    }

    public function testPortCategories(): void
    {
        // Well-known ports (1-1023)
        $port = Port::fromNative('22');
        $this->assertTrue($port->isWellKnown());
        $this->assertFalse($port->isRegistered());
        $this->assertFalse($port->isDynamic());

        // Registered ports (1024-49151)
        $port = Port::fromNative('8080');
        $this->assertFalse($port->isWellKnown());
        $this->assertTrue($port->isRegistered());
        $this->assertFalse($port->isDynamic());

        // Dynamic ports (49152-65535)
        $port = Port::fromNative('60000');
        $this->assertFalse($port->isWellKnown());
        $this->assertFalse($port->isRegistered());
        $this->assertTrue($port->isDynamic());
    }
} 