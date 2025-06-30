<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\ConfigLoader;
use dzentota\ConfigLoader\ConfigLoaderFactory;
use dzentota\ConfigLoader\Source\ArraySource;
use dzentota\ConfigLoader\TypedValue\Port;
use dzentota\ConfigLoader\TypedValue\FeatureFlag;
use dzentota\ConfigLoader\TypedValue\ServiceUrl;
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
        $config = [
            'valid_port' => '3000',
            'invalid_port' => 'abc'
        ];
        $loader = ConfigLoaderFactory::createForTesting($config);

        $this->assertTrue($loader->isValid('valid_port', Port::class));
        $this->assertFalse($loader->isValid('invalid_port', Port::class));
        $this->assertFalse($loader->isValid('missing_key', Port::class));
    }

    public function testClearCache(): void
    {
        $source = new ArraySource(['port' => '3000'], 100, 'Test');
        $loader = new ConfigLoader();
        $loader->addSource($source);

        // Load config
        $this->assertTrue($loader->has('port'));

        // Modify source data
        $source->set('port', '4000');
        
        // Should still have old value due to caching
        $port = $loader->get('port', Port::class);
        $this->assertEquals(3000, $port->toInt());

        // Clear cache and reload
        $loader->clearCache();
        $port = $loader->get('port', Port::class);
        $this->assertEquals(4000, $port->toInt());
    }

    public function testGetSourceInfo(): void
    {
        $loader = new ConfigLoader();
        $loader->addSource(new ArraySource([], 100, 'Test Source'));

        $sourceInfo = $loader->getSourceInfo();
        $this->assertCount(1, $sourceInfo);
        $this->assertEquals('Test Source', $sourceInfo[0]['name']);
        $this->assertEquals(100, $sourceInfo[0]['priority']);
    }
} 