<?php

declare(strict_types=1);

/**
 * Example: Web Application Configuration
 * 
 * This example demonstrates how to use the ConfigLoader in a real web application
 * following the AppSec Manifesto principles.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use dzentota\ConfigLoader\ConfigLoader;
use dzentota\ConfigLoader\ConfigLoaderFactory;
use DomainPrimitives\Network\Port;
use DomainPrimitives\Network\ServiceUrl;
use DomainPrimitives\Database\DatabaseDsn;
use DomainPrimitives\Configuration\FeatureFlag;
use dzentota\ConfigLoader\Exception\ConfigLoaderException;

/**
 * Custom TypedValue for API keys with additional validation
 */
use dzentota\TypedValue\Typed;
use dzentota\TypedValue\TypedValue;
use dzentota\TypedValue\ValidationResult;

class ApiKey implements Typed
{
    use TypedValue;

    public static function validate($value): ValidationResult
    {
        $result = new ValidationResult();

        if (!is_string($value)) {
            $result->addError('API key must be a string');
            return $result;
        }

        if (strlen($value) < 32) {
            $result->addError('API key must be at least 32 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            $result->addError('API key contains invalid characters');
        }

        return $result;
    }

    public function isSandbox(): bool
    {
        return str_starts_with($this->toNative(), 'sk_test_');
    }

    public function isLive(): bool
    {
        return str_starts_with($this->toNative(), 'sk_live_');
    }
}

/**
 * Application Configuration Class
 */
class AppConfig
{
    private ConfigLoader $configLoader;

    public function __construct(ConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * Get server configuration
     */
    public function getServerConfig(): array
    {
        return [
            'port' => $this->configLoader->get('port', Port::class),
            'host' => $this->configLoader->getRaw('host', 'localhost'),
            'debug' => $this->configLoader->get('debug', FeatureFlag::class),
        ];
    }

    /**
     * Get database configuration
     */
    public function getDatabaseConfig(): array
    {
        return [
            'dsn' => $this->configLoader->get('database_dsn', DatabaseDsn::class),
            'pool_size' => $this->configLoader->getRaw('db_pool_size', 10),
        ];
    }

    /**
     * Get external services configuration
     */
    public function getServicesConfig(): array
    {
        return [
            'api_url' => $this->configLoader->get('api_url', ServiceUrl::class),
            'webhook_url' => $this->configLoader->get('webhook_url', ServiceUrl::class),
            'stripe_key' => $this->configLoader->get('stripe_key', ApiKey::class),
        ];
    }

    /**
     * Get feature flags
     */
    public function getFeatureFlags(): array
    {
        return [
            'maintenance_mode' => $this->configLoader->get('maintenance_mode', FeatureFlag::class),
            'new_dashboard' => $this->configLoader->get('new_dashboard', FeatureFlag::class),
            'email_notifications' => $this->configLoader->get('email_notifications', FeatureFlag::class),
        ];
    }
}

/**
 * Web Application Bootstrap
 */
class WebApplication
{
    private AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Initialize and run the web application
     */
    public function run(): void
    {
        try {
            $this->validateConfiguration();
            $this->startServer();
        } catch (ConfigLoaderException $e) {
            error_log("Configuration error: " . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Validate all configuration at startup
     */
    private function validateConfiguration(): void
    {
        $serverConfig = $this->config->getServerConfig();
        $dbConfig = $this->config->getDatabaseConfig();
        $servicesConfig = $this->config->getServicesConfig();

        echo "✓ Server will run on port: " . $serverConfig['port']->toInt() . "\n";
        echo "✓ Database: " . $dbConfig['dsn']->getDriver() . "\n";
        echo "✓ API URL: " . $servicesConfig['api_url']->getHost() . "\n";
        echo "✓ Stripe environment: " . 
            ($servicesConfig['stripe_key']->isSandbox() ? 'sandbox' : 'live') . "\n";
    }

    /**
     * Start the server (mock implementation)
     */
    private function startServer(): void
    {
        $serverConfig = $this->config->getServerConfig();
        
        echo "Starting server on port " . $serverConfig['port']->toInt() . "...\n";
        
        if ($serverConfig['debug']->isEnabled()) {
            echo "Debug mode enabled\n";
        }
        
        echo "Application started successfully!\n";
    }
}

/**
 * Example usage
 */
try {
    // Create configuration loader with defaults, JSON file, and environment variables
    $configLoader = ConfigLoaderFactory::createLayered(
        [
            // Secure defaults
            'port' => '8080',
            'host' => 'localhost',
            'debug' => 'false',
            'database_dsn' => 'sqlite::memory:',
            'api_url' => 'https://api.example.com',
            'webhook_url' => 'https://webhooks.example.com',
            'stripe_key' => 'sk_test_' . str_repeat('a', 32),
            'maintenance_mode' => 'false',
            'new_dashboard' => 'false',
            'email_notifications' => 'true',
            'db_pool_size' => '10'
        ],
        '/etc/myapp/config.json',   // Production config file (optional)
        'MYAPP_',                   // Environment variable prefix
        true,                       // Strict mode
        false                       // Config file not required
    );

    // Create application configuration
    $appConfig = new AppConfig($configLoader);

    // Create and run the web application
    $webApp = new WebApplication($appConfig);
    $webApp->run();

} catch (ConfigLoaderException $e) {
    echo "Fatal configuration error: " . $e->getMessage() . "\n";
    exit(1);
} 