# Architecture Documentation

## Overview

The ConfigLoader library is a security-first configuration management system that implements the "Parse, Don't Validate" pattern using TypedValue objects. The architecture follows the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) principles and provides a layered, extensible system for loading and validating configuration from multiple sources.

## Design Principles

### 1. AppSec Manifesto Compliance
- **Rule #0: Absolute Zero** - Minimize attack surface by only loading required data
- **Rule #2: Parse, Don't Validate** - Immediate parsing into TypedValue objects with fail-fast behavior
- **Rule #3: Forget-me-not** - Preserve data validity through the type system
- **Rule #4: Declaration of Sources Rights** - All sources treated equally with uniform processing
- **Rule #8: The Vigilant Eye** - Comprehensive logging and monitoring of configuration errors

### 2. Core Design Patterns
- **Strategy Pattern** - Pluggable sources and parsers
- **Factory Pattern** - ConfigLoaderFactory for common configurations
- **Chain of Responsibility** - Parser selection based on priority
- **Decorator Pattern** - Layered configuration sources with priority override

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        ConfigLoader                             │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────────────────────────┐   │
│  │  ConfigLoader   │  │      ConfigLoaderFactory         │   │
│  │   (Orchestrator)│  │    (Common Configurations)       │   │
│  └─────────────────┘  └─────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────────────────────────┐   │
│  │   Sources       │  │         Parsers                   │   │
│  │                 │  │                                   │   │
│  │ ┌─────────────┐ │  │ ┌─────────────────────────────────┐ │   │
│  │ │ArraySource  │ │  │ │      DefaultParser            │ │   │
│  │ └─────────────┘ │  │ └─────────────────────────────────┘ │   │
│  │ ┌─────────────┐ │  │ ┌─────────────────────────────────┐ │   │
│  │ │Environment  │ │  │ │      Custom Parsers           │ │   │
│  │ │Source       │ │  │ │     (Extensible)              │ │   │
│  │ └─────────────┘ │  │ └─────────────────────────────────┘ │   │
│  │ ┌─────────────┐ │  └─────────────────────────────────────┘   │
│  │ │JsonFile     │ │                                          │
│  │ │Source       │ │                                          │
│  │ └─────────────┘ │                                          │
│  └─────────────────┘                                          │
├─────────────────────────────────────────────────────────────────┤
│                    Exception Handling                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ ConfigLoaderException → ValidationException             │   │
│  │                      → SourceException                  │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                                 ↓
┌─────────────────────────────────────────────────────────────────┐
│                External Dependencies                           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              dzentota/domain-primitives                │   │
│  │                                                        │   │
│  │ • Port (1-65535 validation)                          │   │
│  │ • ServiceUrl (HTTP/HTTPS validation)                 │   │
│  │ • FeatureFlag (Smart boolean parsing)                │   │
│  │ • DatabaseDsn (Connection string validation)         │   │
│  │ • Custom TypedValue classes                          │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Core Components

### 1. ConfigLoader (Orchestrator)
**Location:** `src/ConfigLoader.php`

**Responsibilities:**
- Coordinate between sources and parsers
- Implement priority-based source merging
- Provide configuration caching
- Handle strict/non-strict mode behavior
- Implement the main API for configuration access

**Key Methods:**
- `get(string $key, string $typedValueClass, mixed $default = null): Typed`
- `getRaw(string $key, mixed $default = null): mixed`
- `has(string $key): bool`
- `addSource(SourceInterface $source): self`
- `addParser(ParserInterface $parser): self`

**Architecture Features:**
- Source priority system (higher priority sources override lower ones)
- Parser chain with priority-based selection
- Configuration caching with cache invalidation
- Strict/non-strict mode for different environments

### 2. ConfigLoaderFactory (Factory)
**Location:** `src/ConfigLoaderFactory.php`

**Responsibilities:**
- Provide convenient factory methods for common configurations
- Implement secure defaults following AppSec Manifesto principles
- Support various deployment patterns (Docker, Twelve-Factor, etc.)

**Factory Methods:**
- `createFromEnvironment(string $envPrefix, bool $strict = true)`
- `createFromJsonAndEnv(string $jsonFilePath, string $envPrefix, bool $strict = true)`
- `createFromMultipleFiles(array $jsonFilePaths, string $envPrefix, bool $strict = true)`
- `createLayered(array $defaults, string $jsonFilePath, string $envPrefix, bool $strict = true)`
- `createTwelveFactor(string $appName, bool $strict = true)`
- `createForDocker(string $envPrefix, string $secretsPath, bool $strict = true)`
- `createForTesting(array $testData)`

### 3. Source System
**Location:** `src/Source/`

All sources implement the `SourceInterface` and follow the "Declaration of Sources Rights" principle.

#### ArraySource
- **Purpose:** In-memory configuration data
- **Use Cases:** Default values, testing, computed configuration
- **Features:** Mutable data, utility methods (merge, set, remove)
- **Security:** No external access, memory-only storage

#### EnvironmentSource
- **Purpose:** Environment variables with optional prefix filtering
- **Use Cases:** Production configuration, Docker deployments
- **Features:** Prefix-based filtering, dual compatibility ($_ENV + getenv())
- **Security:** Environment variable isolation, prefix-based access control

#### JsonFileSource
- **Purpose:** JSON configuration files
- **Use Cases:** Structured configuration, deployment-specific settings
- **Features:** Nested object flattening (dot notation), file caching
- **Security:** File existence validation, JSON syntax validation, readable file checks

**Source Priority System:**
- Lower numbers = lower priority (loaded first)
- Higher numbers = higher priority (override previous values)
- Default priorities: Array (10), JSON (50), Environment (100)

### 4. Parser System
**Location:** `src/Parser/`

#### DefaultParser
- **Purpose:** Universal parser for any TypedValue class
- **Mechanism:** Uses reflection to check Typed interface implementation
- **Features:** Automatic validation, exception wrapping, priority support
- **Extensibility:** Can be extended with custom parsers for specialized validation

**Parser Chain:**
1. Parsers sorted by priority (highest first)
2. First parser that can handle the TypedValue class processes the value
3. Validation exceptions are wrapped with configuration context

### 5. Exception System
**Location:** `src/Exception/`

**Exception Hierarchy:**
```
ConfigLoaderException (base)
├── ValidationException (parsing/validation failures)
└── SourceException (source loading failures)
```

**Exception Features:**
- Contextual error information (config key, raw value, source name)
- Chained exceptions for root cause analysis
- Standardized error messages for logging and debugging

## Data Flow

### 1. Configuration Loading Process

```
User Request → ConfigLoader.get()
                     ↓
              loadConfig() (if not cached)
                     ↓
          Sort sources by priority (low to high)
                     ↓
          For each source: source.load()
                     ↓
          Merge source data (higher priority overwrites)
                     ↓
               Cache result
                     ↓
           Extract requested key value
                     ↓
          Find suitable parser for TypedValue class
                     ↓
          Parse value → TypedValue object
                     ↓
              Return to user
```

### 2. Source Loading Process

```
source.load() called
       ↓
Check cache (if available)
       ↓
Load raw data from source
       ↓
Apply source-specific processing
       ↓
Return key-value pairs
```

### 3. Parser Selection Process

```
parseValue() called
       ↓
Iterate parsers by priority (highest first)
       ↓
Check if parser.canParse(typedValueClass)
       ↓
Call parser.parse(value, typedValueClass)
       ↓
Return TypedValue object or throw ValidationException
```

## Extensibility Points

### 1. Custom Sources
Implement `SourceInterface` to add new configuration sources:

```php
class DatabaseSource implements SourceInterface
{
    public function getPriority(): int { return 30; }
    public function load(): array { /* Implementation */ }
    public function has(string $key): bool { /* Implementation */ }
    public function get(string $key) { /* Implementation */ }
    public function getName(): string { /* Implementation */ }
}
```

### 2. Custom Parsers
Implement `ParserInterface` for specialized validation:

```php
class CustomParser implements ParserInterface
{
    public function canParse(string $typedValueClass): bool { /* Implementation */ }
    public function parse($value, string $typedValueClass): Typed { /* Implementation */ }
    public function getPriority(): int { return 100; }
}
```

### 3. Custom TypedValue Classes
Implement the `Typed` interface for domain-specific validation:

```php
class ApiKey implements Typed
{
    use TypedValue;
    
    public static function validate($value): ValidationResult
    {
        // Custom validation logic
    }
}
```

## Performance Considerations

### 1. Caching Strategy
- **Configuration Caching:** Full configuration cached after first load
- **Source Caching:** Individual sources can implement caching
- **Cache Invalidation:** Cache cleared when sources are modified

### 2. Lazy Loading
- Configuration only loaded when first accessed
- Sources only loaded when configuration is requested
- Parsers only instantiated when needed

### 3. Memory Management
- Cached data stored in memory for fast access
- Sources can implement cache clearing for memory management
- No persistent storage, pure in-memory operations

## Dependencies

### 1. Required Dependencies
- **PHP 8.2+** - Modern PHP features (readonly properties, enums, etc.)
- **dzentota/domain-primitives** - TypedValue implementations
- **ext-json** - JSON parsing for JsonFileSource

### 2. Development Dependencies
- **PHPUnit 9.5** - Testing framework
- **Psalm 4.0** - Static analysis
- **PHPStan 1.0** - Additional static analysis

## Deployment Patterns

### 1. Twelve-Factor App
```php
$loader = ConfigLoaderFactory::createTwelveFactor('myapp');
```

### 2. Docker Deployment
```php
$loader = ConfigLoaderFactory::createForDocker('APP_', '/run/secrets');
```

### 3. Layered Configuration
```php
$loader = ConfigLoaderFactory::createLayered($defaults, $configFile, 'APP_');
```

### 4. Testing Environment
```php
$loader = ConfigLoaderFactory::createForTesting($testData);
```

## Best Practices

### 1. Source Priority Planning
- Use consistent priority ranges (e.g., 10s for defaults, 50s for files, 100s for environment)
- Document priority decisions for team understanding
- Consider override behavior in different environments

### 2. Error Handling
- Always wrap configuration loading in try-catch blocks
- Use strict mode in production, non-strict in development
- Log configuration errors for monitoring

### 3. Security Considerations
- Use prefixed environment variables to avoid conflicts
- Validate file permissions for JSON sources
- Implement proper exception handling to prevent information disclosure

### 4. Performance Optimization
- Load and validate configuration at application startup
- Use factory methods for common patterns
- Implement proper caching strategies for frequently accessed values

## Future Extensibility

The architecture is designed to support future enhancements:

### 1. Additional Sources
- Database configuration sources
- Remote configuration services (etcd, Consul)
- Kubernetes ConfigMaps and Secrets
- Cloud provider configuration services

### 2. Enhanced Parsers
- Specialized parsers for complex data types
- Transformation pipelines for data processing
- Conditional parsing based on environment

### 3. Advanced Features
- Configuration reloading without restart
- Configuration change notifications
- Audit logging for configuration access
- Configuration validation rules engine

## Testing Strategy

### 1. Unit Tests
- 205 tests covering all components
- 797 assertions ensuring comprehensive coverage
- Mock objects for external dependencies

### 2. Integration Tests
- End-to-end configuration loading scenarios
- Multi-source integration testing
- Error handling and recovery testing

### 3. Performance Tests
- Memory usage validation
- Cache performance testing
- Large configuration file handling

This architecture provides a solid foundation for secure, extensible, and maintainable configuration management while adhering to modern PHP development practices and security principles. 