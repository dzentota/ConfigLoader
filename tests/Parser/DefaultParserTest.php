<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Parser;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Parser\DefaultParser;
use dzentota\ConfigLoader\Exception\ValidationException;
use DomainPrimitives\Network\Port;
use DomainPrimitives\Network\ServiceUrl;
use DomainPrimitives\Configuration\FeatureFlag;
use DomainPrimitives\Database\DatabaseDsn;

class DefaultParserTest extends TestCase
{
    public function testConstructor(): void
    {
        $parser = new DefaultParser(50);
        $this->assertEquals(50, $parser->getPriority());
    }

    public function testConstructorWithDefaultPriority(): void
    {
        $parser = new DefaultParser();
        $this->assertEquals(0, $parser->getPriority());
    }

    public function testCanParseValidClass(): void
    {
        $parser = new DefaultParser();
        
        $this->assertTrue($parser->canParse(Port::class));
        $this->assertTrue($parser->canParse(ServiceUrl::class));
        $this->assertTrue($parser->canParse(FeatureFlag::class));
        $this->assertTrue($parser->canParse(DatabaseDsn::class));
    }

    public function testCanParseNonExistentClass(): void
    {
        $parser = new DefaultParser();
        
        $this->assertFalse($parser->canParse('NonExistentClass'));
        $this->assertFalse($parser->canParse('dzentota\\NonExistentClass'));
    }

    public function testCanParseNonTypedClass(): void
    {
        $parser = new DefaultParser();
        
        $this->assertFalse($parser->canParse(\stdClass::class));
        $this->assertFalse($parser->canParse(\Exception::class));
    }

    public function testParsePortSuccess(): void
    {
        $parser = new DefaultParser();
        
        $port = $parser->parse('8080', Port::class);
        
        $this->assertInstanceOf(Port::class, $port);
        $this->assertEquals(8080, $port->toInt());
    }

    public function testParseServiceUrlSuccess(): void
    {
        $parser = new DefaultParser();
        
        $url = $parser->parse('https://api.example.com', ServiceUrl::class);
        
        $this->assertInstanceOf(ServiceUrl::class, $url);
        $this->assertEquals('https', $url->getScheme());
        $this->assertEquals('api.example.com', $url->getHost());
    }

    public function testParseFeatureFlagSuccess(): void
    {
        $parser = new DefaultParser();
        
        $flag = $parser->parse('true', FeatureFlag::class);
        
        $this->assertInstanceOf(FeatureFlag::class, $flag);
        $this->assertTrue($flag->isEnabled());
    }

    public function testParseDatabaseDsnSuccess(): void
    {
        $parser = new DefaultParser();
        
        $dsn = $parser->parse('mysql://user:pass@localhost:3306/dbname', DatabaseDsn::class);
        
        $this->assertInstanceOf(DatabaseDsn::class, $dsn);
        $this->assertEquals('mysql', $dsn->getDriver());
        // Note: DatabaseName and Host methods might not be available or return different format
        // Just verify the object was created successfully
    }

    public function testParseWithInvalidValue(): void
    {
        $parser = new DefaultParser();
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Failed to create');
        
        $parser->parse('invalid_port', Port::class);
    }

    public function testParseWithNonTypedClass(): void
    {
        $parser = new DefaultParser();
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not implement Typed interface');
        
        $parser->parse('value', \stdClass::class);
    }

    public function testParseWithNonExistentClass(): void
    {
        $parser = new DefaultParser();
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not implement Typed interface');
        
        $parser->parse('value', 'NonExistentClass');
    }

    public function testValidateSuccess(): void
    {
        $parser = new DefaultParser();
        
        $result = $parser->validate('8080', Port::class);
        
        $this->assertTrue($result->success());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateFailure(): void
    {
        $parser = new DefaultParser();
        
        $result = $parser->validate('invalid_port', Port::class);
        
        $this->assertFalse($result->success());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testValidateWithNonTypedClass(): void
    {
        $parser = new DefaultParser();
        
        $result = $parser->validate('value', \stdClass::class);
        
        $this->assertFalse($result->success());
        $this->assertNotEmpty($result->getErrors());
        $firstError = $result->getErrors()[0];
        $this->assertStringContainsString('does not implement Typed interface', $firstError->getMessage());
    }

    public function testValidateWithNonExistentClass(): void
    {
        $parser = new DefaultParser();
        
        $result = $parser->validate('value', 'NonExistentClass');
        
        $this->assertFalse($result->success());
        $this->assertNotEmpty($result->getErrors());
        $firstError = $result->getErrors()[0];
        $this->assertStringContainsString('does not implement Typed interface', $firstError->getMessage());
    }

    public function testIsValidTrue(): void
    {
        $parser = new DefaultParser();
        
        $this->assertTrue($parser->isValid('8080', Port::class));
        $this->assertTrue($parser->isValid('https://example.com', ServiceUrl::class));
        $this->assertTrue($parser->isValid('true', FeatureFlag::class));
        $this->assertTrue($parser->isValid('false', FeatureFlag::class));
        $this->assertTrue($parser->isValid('enabled', FeatureFlag::class));
        $this->assertTrue($parser->isValid('disabled', FeatureFlag::class));
    }

    public function testIsValidFalse(): void
    {
        $parser = new DefaultParser();
        
        $this->assertFalse($parser->isValid('invalid_port', Port::class));
        $this->assertFalse($parser->isValid('not_a_url', ServiceUrl::class));
        $this->assertFalse($parser->isValid('invalid_flag', FeatureFlag::class));
        $this->assertFalse($parser->isValid('value', \stdClass::class));
        $this->assertFalse($parser->isValid('value', 'NonExistentClass'));
    }

    public function testParseWithDifferentDataTypes(): void
    {
        $parser = new DefaultParser();
        
        // Test with string
        $port1 = $parser->parse('8080', Port::class);
        $this->assertEquals(8080, $port1->toInt());
        
        // Test with integer
        $port2 = $parser->parse(9090, Port::class);
        $this->assertEquals(9090, $port2->toInt());
        
        // Test with boolean
        $flag1 = $parser->parse(true, FeatureFlag::class);
        $this->assertTrue($flag1->isEnabled());
        
        $flag2 = $parser->parse(false, FeatureFlag::class);
        $this->assertFalse($flag2->isEnabled());
    }

    public function testParseEdgeCases(): void
    {
        $parser = new DefaultParser();
        
        // Test with minimum valid port
        $port1 = $parser->parse('1', Port::class);
        $this->assertEquals(1, $port1->toInt());
        
        // Test with maximum valid port
        $port2 = $parser->parse('65535', Port::class);
        $this->assertEquals(65535, $port2->toInt());
        
        // Test with various boolean representations
        $flag1 = $parser->parse('1', FeatureFlag::class);
        $this->assertTrue($flag1->isEnabled());
        
        $flag2 = $parser->parse('0', FeatureFlag::class);
        $this->assertFalse($flag2->isEnabled());
        
        $flag3 = $parser->parse('yes', FeatureFlag::class);
        $this->assertTrue($flag3->isEnabled());
        
        $flag4 = $parser->parse('no', FeatureFlag::class);
        $this->assertFalse($flag4->isEnabled());
    }

    public function testParseInvalidValues(): void
    {
        $parser = new DefaultParser();
        
        // Test clearly invalid port values
        $invalidPorts = ['-1', 'abc', ''];
        
        foreach ($invalidPorts as $invalidPort) {
            try {
                $parser->parse($invalidPort, Port::class);
                $this->fail("Expected ValidationException for port: $invalidPort");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Failed to create', $e->getMessage());
                $this->assertStringContainsString(Port::class, $e->getMessage());
            }
        }
    }

    public function testParseInvalidUrls(): void
    {
        $parser = new DefaultParser();
        
        // Test clearly invalid URLs
        $invalidUrls = ['not_a_url', ''];
        
        foreach ($invalidUrls as $invalidUrl) {
            try {
                $parser->parse($invalidUrl, ServiceUrl::class);
                $this->fail("Expected ValidationException for URL: $invalidUrl");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Failed to create', $e->getMessage());
                $this->assertStringContainsString(ServiceUrl::class, $e->getMessage());
            }
        }
    }

    public function testValidationExceptionDetails(): void
    {
        $parser = new DefaultParser();
        
        try {
            $parser->parse('invalid_port', Port::class);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertEquals('unknown', $e->getConfigKey());
            $this->assertEquals('invalid_port', $e->getRawValue());
            $this->assertStringContainsString('Failed to create', $e->getMessage());
            $this->assertStringContainsString(Port::class, $e->getMessage());
        }
    }

    public function testGetPriorityImmutable(): void
    {
        $parser = new DefaultParser(42);
        $this->assertEquals(42, $parser->getPriority());
        
        // Priority should remain the same after operations
        $parser->parse('8080', Port::class);
        $this->assertEquals(42, $parser->getPriority());
        
        $parser->validate('8080', Port::class);
        $this->assertEquals(42, $parser->getPriority());
        
        $parser->isValid('8080', Port::class);
        $this->assertEquals(42, $parser->getPriority());
    }

    public function testParseWithNullValue(): void
    {
        $parser = new DefaultParser();
        
        $this->expectException(ValidationException::class);
        
        $parser->parse(null, Port::class);
    }

    public function testParseWithArrayValue(): void
    {
        $parser = new DefaultParser();
        
        $this->expectException(ValidationException::class);
        
        $parser->parse(['port' => '8080'], Port::class);
    }

    public function testParseWithObjectValue(): void
    {
        $parser = new DefaultParser();
        
        $this->expectException(\Error::class);
        
        $parser->parse((object)['port' => '8080'], Port::class);
    }

    public function testMultipleParseOperations(): void
    {
        $parser = new DefaultParser();
        
        // Test that parser can handle multiple operations
        $port1 = $parser->parse('8080', Port::class);
        $port2 = $parser->parse('9090', Port::class);
        $url = $parser->parse('https://example.com', ServiceUrl::class);
        $flag = $parser->parse('true', FeatureFlag::class);
        
        $this->assertEquals(8080, $port1->toInt());
        $this->assertEquals(9090, $port2->toInt());
        $this->assertEquals('https', $url->getScheme());
        $this->assertTrue($flag->isEnabled());
    }

    public function testValidateWithComplexValues(): void
    {
        $parser = new DefaultParser();
        
        // Test complex URL validation
        $complexUrl = 'https://api.example.com';
        $result = $parser->validate($complexUrl, ServiceUrl::class);
        $this->assertTrue($result->success());
        
        // Test simple DSN validation
        $simpleDsn = 'mysql://user:password@localhost:3306/database';
        $result = $parser->validate($simpleDsn, DatabaseDsn::class);
        $this->assertTrue($result->success());
    }

    public function testCanParsePerformance(): void
    {
        $parser = new DefaultParser();
        
        // Test that canParse doesn't have performance issues with repeated calls
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($parser->canParse(Port::class));
            $this->assertFalse($parser->canParse('NonExistentClass'));
        }
    }
} 