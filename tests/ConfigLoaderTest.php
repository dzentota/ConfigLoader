<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\ConfigLoader;
use dzentota\ConfigLoader\ConfigLoaderFactory;
use dzentota\ConfigLoader\Source\ArraySource;
use DomainPrimitives\Network\Port;
use DomainPrimitives\Configuration\FeatureFlag;
use DomainPrimitives\Network\ServiceUrl;
use dzentota\ConfigLoader\Exception\ValidationException;
use dzentota\ConfigLoader\Exception\ConfigLoaderException;

class ConfigLoaderTest extends TestCase
{
    public function testBasicConfigLoading(): void
    {
        $config = [
            'port' => '3000',
            'debug' => 'true',
            'api_url' => 'https://api.example.com'
        ];

        $loader = ConfigLoaderFactory::createForTesting($config);

        $port = $loader->get('port', Port::class);
        $this->assertInstanceOf(Port::class, $port);
        $this->assertEquals(3000, $port->toInt());

        $debug = $loader->get('debug', FeatureFlag::class);
        $this->assertInstanceOf(FeatureFlag::class, $debug);
        $this->assertTrue($debug->isEnabled());

        $url = $loader->get('api_url', ServiceUrl::class);
        $this->assertInstanceOf(ServiceUrl::class, $url);
        $this->assertEquals('https', $url->getScheme());
        $this->assertEquals('api.example.com', $url->getHost());
    }

    public function testValidationFailure(): void
    {
        $config = ['port' => 'invalid'];
        $loader = ConfigLoaderFactory::createForTesting($config);

        $this->expectException(ValidationException::class);
        $loader->get('port', Port::class);
    }

    public function testMissingKeyInStrictMode(): void
    {
        $loader = ConfigLoaderFactory::createForTesting([]);

        $this->expectException(ConfigLoaderException::class);
        $loader->get('missing_key', Port::class);
    }

    public function testMissingKeyInNonStrictMode(): void
    {
        $loader = new ConfigLoader(false);
        $loader->addSource(new ArraySource([], 100, 'Test'));

        $port = $loader->get('missing_key', Port::class, '8080');
        $this->assertEquals(8080, $port->toInt());
    }

    public function testSourcePriority(): void
    {
        $loader = new ConfigLoader();
        
        // Add low priority source
        $loader->addSource(new ArraySource(['port' => '3000'], 10, 'Low'));
        
        // Add high priority source that overrides
        $loader->addSource(new ArraySource(['port' => '4000'], 20, 'High'));

        $port = $loader->get('port', Port::class);
        $this->assertEquals(4000, $port->toInt());
    }

    public function testHasMethod(): void
    {
        $config = ['existing_key' => 'value'];
        $loader = ConfigLoaderFactory::createForTesting($config);

        $this->assertTrue($loader->has('existing_key'));
        $this->assertFalse($loader->has('missing_key'));
    }

    public function testGetKeys(): void
    {
        $config = ['key1' => 'value1', 'key2' => 'value2'];
        $loader = ConfigLoaderFactory::createForTesting($config);

        $keys = $loader->getKeys();
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertCount(2, $keys);
    }

    public function testGetRaw(): void
    {
        $config = ['raw_value' => 'test_string'];
        $loader = ConfigLoaderFactory::createForTesting($config);

        $rawValue = $loader->getRaw('raw_value');
        $this->assertEquals('test_string', $rawValue);
    }

    public function testIsValid(): void
    {
        $config = ['valid_port' => '8080', 'invalid_port' => 'not_a_port'];
        $loader = ConfigLoaderFactory::createForTesting($config);

        $this->assertTrue($loader->isValid('valid_port', Port::class));
        $this->assertFalse($loader->isValid('invalid_port', Port::class));
        $this->assertFalse($loader->isValid('missing_key', Port::class));
    }

    public function testMultipleTypedValues(): void
    {
        $config = [
            'server_port' => '8080',
            'database_port' => '3306',
            'feature_enabled' => 'true',
            'feature_disabled' => 'false',
            'api_url' => 'https://api.example.com/v1',
            'webhook_url' => 'https://webhook.example.com'
        ];

        $loader = ConfigLoaderFactory::createForTesting($config);

        $serverPort = $loader->get('server_port', Port::class);
        $dbPort = $loader->get('database_port', Port::class);
        $featureEnabled = $loader->get('feature_enabled', FeatureFlag::class);
        $featureDisabled = $loader->get('feature_disabled', FeatureFlag::class);
        $apiUrl = $loader->get('api_url', ServiceUrl::class);
        $webhookUrl = $loader->get('webhook_url', ServiceUrl::class);

        $this->assertEquals(8080, $serverPort->toInt());
        $this->assertEquals(3306, $dbPort->toInt());
        $this->assertTrue($featureEnabled->isEnabled());
        $this->assertFalse($featureDisabled->isEnabled());
        $this->assertEquals('api.example.com', $apiUrl->getHost());
        $this->assertEquals('webhook.example.com', $webhookUrl->getHost());
    }

    public function testCachingBehavior(): void
    {
        $config = ['port' => '8080'];
        $loader = ConfigLoaderFactory::createForTesting($config);

        // First load
        $port1 = $loader->get('port', Port::class);
        // Second load (should use cache)
        $port2 = $loader->get('port', Port::class);

        $this->assertEquals($port1->toInt(), $port2->toInt());
    }

    public function testClearCache(): void
    {
        $loader = new ConfigLoader();
        $source = new ArraySource(['port' => '8080'], 100, 'Test');
        $loader->addSource($source);

        // Load once
        $port1 = $loader->get('port', Port::class);
        
        // Clear cache
        $loader->clearCache();
        
        // Load again (should reload from source)
        $port2 = $loader->get('port', Port::class);
        
        $this->assertEquals($port1->toInt(), $port2->toInt());
    }

    public function testStrictModeToggle(): void
    {
        $loader = new ConfigLoader(true);
        $loader->addSource(new ArraySource(['port' => '8080'], 100, 'Test'));

        $this->assertTrue($loader->isStrict());

        // Should throw exception for missing key in strict mode
        $this->expectException(ConfigLoaderException::class);
        $loader->get('missing_key', Port::class);
    }

    public function testNonStrictModeWithDefault(): void
    {
        $loader = new ConfigLoader(false);
        $loader->addSource(new ArraySource(['port' => '8080'], 100, 'Test'));

        $this->assertFalse($loader->isStrict());

        // Should use default value for missing key in non-strict mode
        $port = $loader->get('missing_key', Port::class, '9000');
        $this->assertEquals(9000, $port->toInt());
    }

    public function testGetSourceInfo(): void
    {
        $loader = new ConfigLoader();
        $loader->addSource(new ArraySource(['test' => 'value'], 100, 'TestSource'));

        $sourceInfo = $loader->getSourceInfo();
        $this->assertCount(1, $sourceInfo);
        $this->assertEquals('TestSource', $sourceInfo[0]['name']);
        $this->assertEquals(100, $sourceInfo[0]['priority']);
    }

    public function testGetParserInfo(): void
    {
        $loader = new ConfigLoader();
        
        $parserInfo = $loader->getParserInfo();
        $this->assertNotEmpty($parserInfo);
        $this->assertArrayHasKey('priority', $parserInfo[0]);
        $this->assertArrayHasKey('type', $parserInfo[0]);
    }
} 