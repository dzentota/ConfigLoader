# Security Documentation

## Overview

The ConfigLoader library implements a security-first approach to configuration management, following the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) principles. This document outlines the security measures, threat model, and best practices for secure usage of the library.

## Security Principles

### AppSec Manifesto Compliance

The library strictly adheres to the AppSec Manifesto rules:

#### Rule #0: Absolute Zero
- **Minimized Attack Surface**: Only loads required configuration data
- **Selective Source Loading**: Sources are only loaded when needed
- **Fail-Closed Defaults**: Strict mode enabled by default
- **Minimal Dependencies**: Only essential dependencies are included

#### Rule #2: Parse, Don't Validate
- **Immediate Parsing**: All configuration values are parsed into TypedValue objects at access time
- **Fail-Fast Behavior**: Invalid data causes immediate exceptions
- **No Delayed Validation**: No postponed validation that could be bypassed
- **Type Safety**: Runtime type checking through TypedValue objects

#### Rule #3: Forget-me-not
- **Immutable Values**: TypedValue objects are immutable once created
- **Persistent Validity**: Data remains valid throughout its lifecycle
- **No State Corruption**: Invalid data cannot corrupt valid configuration state
- **Memory Safety**: No buffer overflows or memory corruption risks

#### Rule #4: Declaration of Sources Rights
- **Uniform Processing**: All sources undergo identical security processing
- **Equal Treatment**: No privileged sources with bypassed security
- **Consistent Validation**: Same validation rules apply to all sources
- **Standardized Error Handling**: Uniform error handling across all sources

#### Rule #8: The Vigilant Eye
- **Comprehensive Logging**: All configuration errors are logged
- **Audit Trail**: Configuration access can be monitored
- **Error Tracking**: Detailed error information for security monitoring
- **Failure Notification**: Failed configuration attempts are recorded

## Threat Model

### Identified Threats

#### 1. Configuration Injection
- **Attack Vector**: Malicious configuration values designed to exploit application logic
- **Mitigation**: TypedValue validation prevents injection by enforcing strict data types
- **Example**: Port number "8080; rm -rf /" is rejected during Port validation

#### 2. Environment Variable Pollution
- **Attack Vector**: Malicious environment variables affecting application behavior
- **Mitigation**: Prefix-based environment variable filtering isolates application configuration
- **Example**: Only variables with "APP_" prefix are processed when configured

#### 3. JSON Injection/Deserialization
- **Attack Vector**: Malicious JSON payloads designed to exploit JSON parsing
- **Mitigation**: Strict JSON validation and flattening prevent complex object injection
- **Example**: JSON must be valid and contain only key-value pairs

#### 4. File System Attacks
- **Attack Vector**: Unauthorized file access through configuration file paths
- **Mitigation**: File existence and readability checks prevent unauthorized access
- **Example**: Configuration files must be readable and cannot be symlinks to sensitive files

#### 5. Information Disclosure
- **Attack Vector**: Error messages revealing sensitive system information
- **Mitigation**: Sanitized error messages prevent information leakage
- **Example**: File paths are normalized in error messages

#### 6. Denial of Service
- **Attack Vector**: Resource exhaustion through large configuration files
- **Mitigation**: Reasonable limits on configuration size and complexity
- **Example**: JSON parsing limits and memory usage monitoring

### Attack Scenarios

#### Scenario 1: Malicious Environment Variables
```bash
# Attacker attempts to inject malicious port
export APP_PORT="8080; rm -rf /"

# Result: Port validation fails, application stops with ValidationException
```

#### Scenario 2: JSON Configuration Manipulation
```json
{
  "database": {
    "host": "localhost",
    "port": "3306; DROP TABLE users;",
    "name": "myapp"
  }
}
```
**Result**: Port validation rejects the malicious value, preventing SQL injection.

#### Scenario 3: File Path Traversal
```php
// Attacker attempts directory traversal
$loader = ConfigLoaderFactory::createFromJsonAndEnv(
    "../../../etc/passwd",
    "APP_"
);
```
**Result**: File existence check fails safely, preventing unauthorized file access.

## Security Features

### 1. Input Validation and Sanitization

#### TypedValue Validation
- **Port Numbers**: Validated to be 1-65535 range
- **URLs**: Validated for proper HTTP/HTTPS format
- **Database DSNs**: Validated for proper connection string format
- **Feature Flags**: Validated for proper boolean representation

#### JSON File Security
```php
// File security checks in JsonFileSource
if (!file_exists($this->filePath)) {
    throw new SourceException(/* ... */);
}

if (!is_readable($this->filePath)) {
    throw new SourceException(/* ... */);
}

$data = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new SourceException(/* ... */);
}
```

#### Environment Variable Filtering
```php
// Prefix-based filtering in EnvironmentSource
private function matchesPrefix(string $key): bool
{
    if (empty($this->prefix)) {
        return true;
    }
    return str_starts_with($key, $this->prefix);
}
```

### 2. Secure Exception Handling

#### Information Disclosure Prevention
```php
// Sanitized error messages in ValidationException
parent::__construct(
    sprintf(
        'Validation failed for config key "%s" with value "%s": %s',
        $configKey,
        is_scalar($rawValue) ? (string)$rawValue : gettype($rawValue),
        $message
    )
);
```

#### Context-Aware Error Reporting
- Configuration keys are sanitized in error messages
- Raw values are type-checked before inclusion in errors
- Source names are normalized to prevent path disclosure

### 3. Access Control

#### Environment Variable Prefixing
```php
// Secure environment variable access
$source = new EnvironmentSource('APP_', 100);
// Only processes environment variables starting with 'APP_'
```

#### File Permission Validation
```php
// File access validation in JsonFileSource
if (!is_readable($this->filePath)) {
    throw new SourceException(
        $this->filePath,
        'json_file',
        'JSON configuration file is not readable'
    );
}
```

### 4. Memory Safety

#### Controlled Memory Usage
- Configuration data is cached in memory with controlled size
- Sources implement cache clearing for memory management
- No persistent storage reduces attack surface

#### Immutable Data Structures
- TypedValue objects are immutable once created
- Configuration cache is read-only after loading
- No mutable global state

## Component Security Analysis

### 1. ConfigLoader (Core)

**Security Features:**
- Strict mode enforcement for production environments
- Source priority system prevents privilege escalation
- Parser chain validation ensures type safety
- Configuration caching prevents repeated source access

**Security Considerations:**
- Memory usage monitoring for large configurations
- Cache invalidation on source changes
- Exception handling prevents information disclosure

### 2. Source Components

#### ArraySource
- **Security Level**: High (memory-only, no external access)
- **Threats**: Memory corruption (mitigated by PHP's memory safety)
- **Validation**: Input data type checking

#### EnvironmentSource
- **Security Level**: Medium (depends on environment security)
- **Threats**: Environment variable pollution
- **Validation**: Prefix-based filtering, dual-source validation

#### JsonFileSource
- **Security Level**: Medium (file system access required)
- **Threats**: File system attacks, JSON injection
- **Validation**: File permission checks, JSON syntax validation

### 3. Parser Components

#### DefaultParser
- **Security Level**: High (reflection-based type checking)
- **Threats**: Deserialization attacks (mitigated by TypedValue validation)
- **Validation**: Interface compliance checking, exception wrapping

### 4. Exception System

- **Information Disclosure**: Sanitized error messages
- **Context Preservation**: Maintains security context in error chains
- **Logging Safety**: Safe error logging without sensitive data exposure

## Security Configuration

### 1. Production Security Settings

```php
// Secure production configuration
$loader = ConfigLoaderFactory::createLayered(
    $secureDefaults,              // Secure default values
    '/etc/myapp/config.json',     // Protected configuration file
    'MYAPP_',                     // Prefixed environment variables
    true,                         // Strict mode enabled
    false                         // Config file optional (fail-safe)
);
```

### 2. Development Security Settings

```php
// Development configuration with relaxed security
$loader = ConfigLoaderFactory::createForTesting([
    'debug' => 'true',
    'port' => '8080',
    // ... other test values
]);
```

### 3. Docker Security Configuration

```php
// Docker deployment with secrets support
$loader = ConfigLoaderFactory::createForDocker(
    'APP_',                       // Environment prefix
    '/run/secrets',               // Docker secrets path
    true                          // Strict mode
);
```

## Secure Usage Guidelines

### 1. Environment Variable Security

```php
// ✅ SECURE: Use prefixed environment variables
$loader = ConfigLoaderFactory::createFromEnvironment('MYAPP_');

// ❌ INSECURE: No prefix allows pollution
$loader = ConfigLoaderFactory::createFromEnvironment('');
```

### 2. File Configuration Security

```php
// ✅ SECURE: Validate file permissions
$configFile = '/etc/myapp/config.json';
if (!is_readable($configFile)) {
    throw new SecurityException('Config file not readable');
}

// ✅ SECURE: Use absolute paths
$loader = ConfigLoaderFactory::createFromJsonAndEnv(
    '/etc/myapp/config.json',
    'MYAPP_'
);

// ❌ INSECURE: Relative paths can be manipulated
$loader = ConfigLoaderFactory::createFromJsonAndEnv(
    '../config.json',
    'MYAPP_'
);
```

### 3. Error Handling Security

```php
// ✅ SECURE: Catch and log appropriately
try {
    $config = $loader->get('database_url', DatabaseDsn::class);
} catch (ValidationException $e) {
    // Log error without exposing sensitive data
    error_log("Configuration validation failed: " . $e->getConfigKey());
    throw new ApplicationException("Invalid configuration");
}

// ❌ INSECURE: Expose detailed error messages
try {
    $config = $loader->get('database_url', DatabaseDsn::class);
} catch (ValidationException $e) {
    // This exposes raw configuration values
    echo "Error: " . $e->getMessage();
}
```

### 4. Type Safety Security

```php
// ✅ SECURE: Always use TypedValue classes
$port = $loader->get('port', Port::class);
$dbUrl = $loader->get('database_url', DatabaseDsn::class);

// ❌ INSECURE: Raw values bypass validation
$port = $loader->getRaw('port');  // Could be malicious string
$dbUrl = $loader->getRaw('database_url');  // Could be injection payload
```

## Security Testing

### 1. Input Validation Testing

```php
// Test malicious port values
$testCases = [
    'port' => [
        '80; rm -rf /',
        '0',
        '65536',
        '-1',
        'invalid',
        '<?php system("rm -rf /"); ?>'
    ]
];

foreach ($testCases['port'] as $maliciousPort) {
    $this->expectException(ValidationException::class);
    $loader->get('port', Port::class);
}
```

### 2. Environment Variable Testing

```php
// Test environment variable pollution
putenv('PATH=/malicious/path');
putenv('APP_PORT=8080');

$loader = ConfigLoaderFactory::createFromEnvironment('APP_');

// Should not be affected by PATH pollution
$port = $loader->get('PORT', Port::class);
$this->assertEquals(8080, $port->toInt());
```

### 3. File System Security Testing

```php
// Test file traversal prevention
$maliciousPaths = [
    '../../../etc/passwd',
    '/etc/shadow',
    '../../config.json',
    'config.json.bak'
];

foreach ($maliciousPaths as $path) {
    $this->expectException(SourceException::class);
    ConfigLoaderFactory::createFromJsonAndEnv($path, 'APP_');
}
```

## Security Monitoring

### 1. Configuration Access Logging

```php
// Log configuration access for monitoring
class AuditingConfigLoader extends ConfigLoader
{
    public function get(string $key, string $typedValueClass, mixed $default = null): Typed
    {
        $this->auditLog->info("Configuration access", [
            'key' => $key,
            'type' => $typedValueClass,
            'timestamp' => time(),
            'source' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ]);
        
        return parent::get($key, $typedValueClass, $default);
    }
}
```

### 2. Security Event Monitoring

```php
// Monitor security-relevant events
try {
    $config = $loader->get('api_key', ApiKey::class);
} catch (ValidationException $e) {
    $this->securityLogger->warning("Configuration validation failed", [
        'key' => $e->getConfigKey(),
        'type' => get_class($e),
        'timestamp' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}
```

## Vulnerability Reporting

### 1. Reporting Process

If you discover a security vulnerability in the ConfigLoader library:

1. **Do NOT** create a public GitHub issue
2. **Do NOT** discuss the vulnerability publicly
3. **Do** send an email to the security team at: `security@dzentota.com`
4. **Do** include:
   - Detailed description of the vulnerability
   - Steps to reproduce the issue
   - Potential impact assessment
   - Suggested fix (if available)

### 2. Response Timeline

- **24 hours**: Acknowledgment of vulnerability report
- **72 hours**: Initial assessment and severity classification
- **1 week**: Detailed analysis and fix development
- **2 weeks**: Security patch release and public disclosure

### 3. Supported Versions

Security updates are provided for:
- Latest stable version
- Previous stable version (if less than 6 months old)
- LTS versions (if designated)

## Security Best Practices Summary

### For Application Developers

1. **Always use strict mode** in production environments
2. **Validate file permissions** before loading configuration files
3. **Use prefixed environment variables** to prevent pollution
4. **Implement proper error handling** to prevent information disclosure
5. **Load configuration early** in application lifecycle
6. **Use TypedValue classes** for all configuration values
7. **Monitor configuration access** for security events

### For DevOps/Infrastructure

1. **Secure configuration file permissions** (600 or 640)
2. **Use environment variable prefixes** in deployment scripts
3. **Implement configuration validation** in CI/CD pipelines
4. **Monitor configuration errors** in production
5. **Use Docker secrets** for sensitive configuration
6. **Implement configuration backup** and recovery procedures

### For Security Teams

1. **Regular security audits** of configuration sources
2. **Penetration testing** of configuration injection vectors
3. **Code review** of custom TypedValue classes
4. **Monitor for security updates** to dependencies
5. **Implement incident response** procedures for configuration breaches

## Conclusion

The ConfigLoader library provides a secure foundation for configuration management through:

- **Defense in Depth**: Multiple layers of security validation
- **Fail-Safe Defaults**: Secure configuration out of the box
- **Type Safety**: Runtime type checking prevents injection attacks
- **Audit Trail**: Comprehensive logging for security monitoring
- **Minimal Attack Surface**: Only loads necessary configuration data

By following this security guide and implementing the recommended practices, applications can achieve robust configuration security while maintaining operational flexibility. 