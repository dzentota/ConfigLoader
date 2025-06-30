<?php

declare(strict_types=1);

/**
 * Example: Web Application Configuration
 * 
 * This example demonstrates how to use the ConfigLoader in a real web application
 * following the AppSec Manifesto principles.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use dzentota\ConfigLoader\ConfigLoaderFactory;
use dzentota\ConfigLoader\TypedValue\Port;
use dzentota\ConfigLoader\TypedValue\ServiceUrl;
use dzentota\ConfigLoader\TypedValue\DatabaseDsn;
use dzentota\ConfigLoader\TypedValue\FeatureFlag;
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
    public function __construct(
        public readonly Port $port,
        public readonly DatabaseDsn $database,
        public readonly ServiceUrl $apiUrl,
        public readonly ApiKey $stripeKey,
        public readonly FeatureFlag $debugMode,
        public readonly FeatureFlag $maintenance
    ) {}

    public function isProduction(): bool
    {
        return !$this->debugMode->isEnabled() && $this->stripeKey->isLive();
    }
}

/**
 * Application Bootstrap
 */
function bootstrapApplication(): AppConfig
{
    try {
        // Define secure defaults
        $defaults = [
            'port' => '8080',
            'debug' => 'false',
            'maintenance' => 'false',
            'DATABASE_DSN' => 'sqlite::memory:',
            'API_URL' => 'https://api.localhost:8080',
            'STRIPE_SECRET_KEY' => 'sk_test_default123456789012345678901234567890',
            'API_TIMEOUT' => '30'
        ];

        // Create loader with layered configuration
        // Priority: Environment > Config File > Defaults
        $loader = ConfigLoaderFactory::createLayered(
            $defaults,
            '/etc/myapp/config.json',  // Production config
            'MYAPP_',                  // Environment prefix
            true,                      // Strict mode
            false                      // Config file optional
        );

        // Load and validate all configuration at startup
        // Following AppSec Manifesto Rule #2: Parse, don't validate
        $config = new AppConfig(
            port: $loader->get('port', Port::class),
            database: $loader->get('DATABASE_DSN', DatabaseDsn::class),
            apiUrl: $loader->get('API_URL', ServiceUrl::class),
            stripeKey: $loader->get('STRIPE_SECRET_KEY', ApiKey::class),
            debugMode: $loader->get('debug', FeatureFlag::class),
            maintenance: $loader->get('maintenance', FeatureFlag::class)
        );

        // Security validation for production
        if ($config->isProduction()) {
            // Ensure secure settings in production
            if ($config->port->toInt() < 1024 && !$config->debugMode->isEnabled()) {
                throw new ConfigLoaderException(
                    'Production applications should not run on privileged ports without explicit debug mode'
                );
            }

            if (!$config->apiUrl->isSecure()) {
                throw new ConfigLoaderException(
                    'Production API URL must use HTTPS'
                );
            }

            if ($config->database->getHost() === 'localhost' && !$config->debugMode->isEnabled()) {
                echo "WARNING: Using localhost database in production mode\n";
            }
        }

        return $config;

    } catch (ConfigLoaderException $e) {
        // Following AppSec Manifesto Rule #8: The Vigilant Eye
        error_log("Configuration error: " . $e->getMessage());
        
        // In production, exit on configuration errors
        if (!isset($_ENV['MYAPP_DEBUG']) || $_ENV['MYAPP_DEBUG'] !== 'true') {
            exit(1);
        }
        
        throw $e;
    }
}

/**
 * Simple Web Application
 */
class WebApplication
{
    private AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    public function run(): void
    {
        if ($this->config->maintenance->isEnabled()) {
            $this->showMaintenancePage();
            return;
        }

        echo "Starting web server on port " . $this->config->port->toInt() . "\n";
        echo "Database: " . $this->config->database->getDriver() . "\n";
        echo "API URL: " . $this->config->apiUrl->toNative() . "\n";
        echo "Debug mode: " . ($this->config->debugMode->isEnabled() ? 'ON' : 'OFF') . "\n";
        echo "Environment: " . ($this->config->isProduction() ? 'PRODUCTION' : 'DEVELOPMENT') . "\n";

        if ($this->config->debugMode->isEnabled()) {
            $this->showDebugInfo();
        }

        // Start your web server here...
        echo "Application started successfully!\n";
    }

    private function showMaintenancePage(): void
    {
        echo "Application is in maintenance mode. Please try again later.\n";
    }

    private function showDebugInfo(): void
    {
        echo "\n=== DEBUG INFO ===\n";
        echo "Stripe Key Type: " . ($this->config->stripeKey->isSandbox() ? 'SANDBOX' : 'LIVE') . "\n";
        echo "Database Host: " . ($this->config->database->getHost() ?? 'N/A') . "\n";
        echo "API Secure: " . ($this->config->apiUrl->isSecure() ? 'YES' : 'NO') . "\n";
        echo "=================\n\n";
    }
}

// Example usage
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "Web Application Configuration Example\n";
    echo "=====================================\n\n";

    // Set some example environment variables (comment out to test real env vars)
    if (getenv('MYAPP_PORT') === false) $_ENV['MYAPP_PORT'] = '3000';
    if (getenv('MYAPP_DEBUG') === false) $_ENV['MYAPP_DEBUG'] = 'true';
    if (getenv('MYAPP_API_URL') === false) $_ENV['MYAPP_API_URL'] = 'https://api.example.com/v1';
    if (getenv('MYAPP_STRIPE_SECRET_KEY') === false) $_ENV['MYAPP_STRIPE_SECRET_KEY'] = 'sk_test_abcdefghijklmnopqrstuvwxyz1234567890';
    if (getenv('MYAPP_DATABASE_DSN') === false) $_ENV['MYAPP_DATABASE_DSN'] = 'mysql:host=localhost;dbname=myapp;port=3306';

    try {
        $config = bootstrapApplication();
        $app = new WebApplication($config);
        $app->run();
    } catch (Exception $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        exit(1);
    }
} 