# ConfigLoader

A secure environment variable and configuration loader that parses values into [TypedValue](https://github.com/dzentota/TypedValue) objects, ensuring type safety and validation from the very start.

This library implements the principles from the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto), providing a security-first approach to configuration management.

## Key Features

- **Parse, Don't Validate**: Configuration values are immediately parsed into typed objects
- **Multiple Sources**: Environment variables, JSON files, arrays with configurable priority
- **Type Safety**: All configuration values are validated using TypedValue objects
- **Security First**: Follows AppSec Manifesto principles for robust application security
- **Flexible**: Extensible parser and source system
- **Production Ready**: Comprehensive error handling and logging

## Security Principles

This library follows the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) rules:

- **Rule #0: Absolute Zero** - Minimize attack surface by only loading what's needed
- **Rule #2: Parse, Don't Validate** - Immediate parsing into TypedValue objects with fail-fast behavior
- **Rule #3: Forget-me-not** - Preserve data validity through the type system
- **Rule #4: Declaration of Sources Rights** - All sources treated equally with uniform processing
- **Rule #8: The Vigilant Eye** - Comprehensive logging and monitoring of configuration errors

## Installation

```bash
composer require dzentota/config-loader
```

## Quick Start

### Basic Usage with Environment Variables

```php
use dzentota\ConfigLoader\ConfigLoaderFactory;
use DomainPrimitives\Network\Port;
use DomainPrimitives\Network\ServiceUrl;

// Create loader that reads environment variables with APP_ prefix
$loader = ConfigLoaderFactory::createFromEnvironment('APP_');

// Get a port number - automatically validated as 1-65535
$port = $loader->get('PORT', Port::class); // Reads APP_PORT
echo $port->toInt(); // 3000

// Get a service URL - automatically validated as proper URL
$apiUrl = $loader->get('API_URL', ServiceUrl::class); // Reads APP_API_URL
echo $apiUrl->getHost(); // api.example.com
```

### Using JSON Configuration with Environment Override

```php
use dzentota\ConfigLoader\ConfigLoaderFactory;
use DomainPrimitives\Database\DatabaseDsn;
use DomainPrimitives\Configuration\FeatureFlag;

// Create loader: JSON file + environment variables (env takes priority)
$loader = ConfigLoaderFactory::createFromJsonAndEnv(
    '/path/to/config.json',
    'APP_'
);

// Values from JSON can be overridden by environment variables
$dbDsn = $loader->get('database.dsn', DatabaseDsn::class);
$debugMode = $loader->get('debug', FeatureFlag::class);

echo $dbDsn->getHost(); // e.g., localhost
echo $debugMode->isEnabled() ? 'Debug ON' : 'Debug OFF';
```

### Layered Configuration (Defaults → File → Environment)

```php
$defaults = [
    'port' => '8080',
    'debug' => 'false',
    'database.host' => 'localhost'
];

$loader = ConfigLoaderFactory::createLayered(
    $defaults,
    '/etc/myapp/config.json',
    'MYAPP_'
);

// Priority: environment > JSON file > defaults
$port = $loader->get('port', Port::class);
```

## Built-in TypedValue Classes

The library includes several pre-built TypedValue classes for common configuration values:

### Port
Validates port numbers (1-65535):

```php
$port = $loader->get('port', Port::class);
echo $port->toInt(); // 3000
echo $port->isWellKnown() ? 'Well-known port' : 'Not well-known';
echo $port->isRegistered() ? 'Registered port' : 'Not registered';
```

### ServiceUrl
Validates HTTP/HTTPS URLs:

```php
$url = $loader->get('api_url', ServiceUrl::class);
echo $url->getScheme(); // https
echo $url->getHost(); // api.example.com
echo $url->getPort(); // 443
echo $url->isSecure() ? 'Secure' : 'Not secure';
```

### FeatureFlag
Smart boolean parsing with multiple formats:

```php
// Supports: true/false, 1/0, yes/no, on/off, enabled/disabled
$flag = $loader->get('feature_enabled', FeatureFlag::class);
echo $flag->isEnabled() ? 'Enabled' : 'Disabled';
```

### DatabaseDsn
Validates database connection strings:

```php
$dsn = $loader->get('database_url', DatabaseDsn::class);
echo $dsn->getDriver(); // mysql
echo $dsn->getHost(); // localhost
echo $dsn->getDatabaseName(); // myapp
echo $dsn->getPort(); // 3306
```

## Creating Custom TypedValue Classes

Create your own typed configuration values:

```php
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
        return str_starts_with($this->get(), 'sk_test_');
    }
}

// Usage
$apiKey = $loader->get('stripe_key', ApiKey::class);
if ($apiKey->isSandbox()) {
    echo "Using Stripe sandbox";
}
```

## Configuration Sources

### Environment Variables

```php
use dzentota\ConfigLoader\Source\EnvironmentSource;

$source = new EnvironmentSource('APP_', 100); // prefix, priority
$loader->addSource($source);
```

### JSON Files

```php
use dzentota\ConfigLoader\Source\JsonFileSource;

$source = new JsonFileSource('/path/to/config.json', 50, true); // path, priority, required
$loader->addSource($source);
```

### In-Memory Arrays

```php
use dzentota\ConfigLoader\Source\ArraySource;

$config = ['port' => '3000', 'debug' => 'true'];
$source = new ArraySource($config, 10, 'Defaults');
$loader->addSource($source);
```

## Factory Methods

The `ConfigLoaderFactory` provides convenient methods for common setups:

```php
// Environment variables only
$loader = ConfigLoaderFactory::createFromEnvironment('APP_');

// JSON + Environment
$loader = ConfigLoaderFactory::createFromJsonAndEnv('/path/config.json', 'APP_');

// Multiple files + Environment
$loader = ConfigLoaderFactory::createFromMultipleFiles([
    '/etc/app/defaults.json',
    '/etc/app/production.json'
], 'APP_');

// Twelve-factor app (environment only)
$loader = ConfigLoaderFactory::createTwelveFactor('myapp');

// Docker with secrets support
$loader = ConfigLoaderFactory::createForDocker('APP_', '/run/secrets');

// Testing
$loader = ConfigLoaderFactory::createForTesting(['port' => '3000']);
```

## Error Handling

The library provides comprehensive error handling:

```php
use dzentota\ConfigLoader\Exception\ValidationException;
use dzentota\ConfigLoader\Exception\SourceException;
use dzentota\ConfigLoader\Exception\ConfigLoaderException;

try {
    $port = $loader->get('invalid_port', Port::class);
} catch (ValidationException $e) {
    echo "Validation failed for key: " . $e->getConfigKey();
    echo "Raw value: " . $e->getRawValue();
} catch (SourceException $e) {
    echo "Source error: " . $e->getSourceName();
} catch (ConfigLoaderException $e) {
    echo "General config error: " . $e->getMessage();
}
```

## Strict vs Non-Strict Mode

```php
// Strict mode (default): throws exceptions for missing keys and source errors
$loader = new ConfigLoader(true);

// Non-strict mode: returns defaults, logs errors
$loader = new ConfigLoader(false);
$port = $loader->get('missing_key', Port::class, '8080'); // Returns default
```

## Advanced Usage

### Custom Parsers

Create specialized parsers for complex validation logic:

```php
use dzentota\ConfigLoader\ParserInterface;
use dzentota\TypedValue\Typed;

class CustomParser implements ParserInterface
{
    public function canParse(string $typedValueClass): bool
    {
        return $typedValueClass === MyCustomType::class;
    }

    public function parse($value, string $typedValueClass): Typed
    {
        // Custom parsing logic
        return new $typedValueClass($value);
    }

    public function getPriority(): int
    {
        return 100; // Higher priority than default parser
    }
}

$loader->addParser(new CustomParser());
```

### Debugging Configuration

```php
// Get information about sources and their priorities
$sources = $loader->getSourceInfo();
foreach ($sources as $source) {
    echo "Source: {$source['name']} (Priority: {$source['priority']})";
}

// Get all configuration keys
$keys = $loader->getKeys();

// Get raw configuration data
$allConfig = $loader->getAllRaw();

// Check if a key would be valid
if ($loader->isValid('port', Port::class)) {
    $port = $loader->get('port', Port::class);
}
```

## Best Practices

1. **Use Prefixed Environment Variables**: Avoid conflicts with system variables
2. **Validate Early**: Load and validate configuration at application startup
3. **Use Typed Values**: Always specify TypedValue classes for validation
4. **Layer Your Sources**: defaults → files → environment variables
5. **Handle Errors Gracefully**: Use try-catch blocks around configuration loading
6. **Test Your Configuration**: Use the testing factory for unit tests

## Example: Production Application Setup

```php
use dzentota\ConfigLoader\ConfigLoaderFactory;
use DomainPrimitives\Network\{Port, ServiceUrl};
use DomainPrimitives\Database\DatabaseDsn;
use DomainPrimitives\Configuration\FeatureFlag;

// Production-ready configuration loading
$loader = ConfigLoaderFactory::createLayered(
    [
        // Secure defaults
        'port' => '8080',
        'debug' => 'false',
        'database.dsn' => 'sqlite::memory:',
        'api.timeout' => '30'
    ],
    '/etc/myapp/config.json',   // Production config file
    'MYAPP_',                   // Environment variable prefix
    true,                       // Strict mode
    false                       // Config file not required
);

try {
    // Load and validate all configuration at startup
    $config = [
        'port' => $loader->get('port', Port::class),
        'debug' => $loader->get('debug', FeatureFlag::class),
        'database' => $loader->get('database.dsn', DatabaseDsn::class),
        'api_url' => $loader->get('api.url', ServiceUrl::class),
    ];
    
    // Configuration is now guaranteed to be valid
    $app = new Application($config);
    $app->run();
    
} catch (ConfigLoaderException $e) {
    error_log("Configuration error: " . $e->getMessage());
    exit(1);
}
```

## Testing

```php
use dzentota\ConfigLoader\ConfigLoaderFactory;

// In your tests
$testConfig = [
    'port' => '3000',
    'debug' => 'true',
    'database.dsn' => 'sqlite::memory:'
];

$loader = ConfigLoaderFactory::createForTesting($testConfig);

// Test your application with known configuration
$app = new Application($loader);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License. See LICENSE file for details.

## Related Libraries

- [TypedValue](https://github.com/dzentota/TypedValue) - Type-safe value objects for PHP
- [Router](https://github.com/dzentota/router) - Security-aware router using TypedValue
- [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) - Security principles this library follows 