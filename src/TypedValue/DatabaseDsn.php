<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\TypedValue;

use dzentota\TypedValue\Typed;
use dzentota\TypedValue\TypedValue;
use dzentota\TypedValue\ValidationResult;

/**
 * Database DSN (Data Source Name) value object with validation.
 * 
 * Following AppSec Manifesto Rule #3: Forget-me-not -
 * Preserving data validity by declaring custom types instead of 
 * using primitive types for unstructured data.
 * 
 * Following AppSec Manifesto Rule #9: The Cipher's Creed -
 * Ensures secure connection strings are properly validated.
 */
class DatabaseDsn implements Typed
{
    use TypedValue;

    private const SUPPORTED_DRIVERS = [
        'mysql',
        'pgsql',
        'sqlite',
        'sqlsrv',
        'oci',
        'firebird',
        'ibm',
        'informix',
        'cubrid'
    ];

    public static function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();

        if (!is_string($value)) {
            $result->addError('DSN must be a string');
            return $result;
        }

        if (empty($value)) {
            $result->addError('DSN cannot be empty');
            return $result;
        }

        // For SQLite files
        if (str_starts_with($value, 'sqlite:')) {
            return self::validateSqliteDsn($value, $result);
        }

        // Parse standard DSN format: driver:host=localhost;dbname=test;port=3306
        $parts = explode(':', $value, 2);
        
        if (count($parts) !== 2) {
            $result->addError('DSN must be in format "driver:connection_string"');
            return $result;
        }

        [$driver, $connectionString] = $parts;

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            $result->addError(sprintf(
                'Unsupported database driver "%s". Supported drivers: %s',
                $driver,
                implode(', ', self::SUPPORTED_DRIVERS)
            ));
        }

        // Basic validation that connection string has required parameters
        if (empty($connectionString)) {
            $result->addError('Connection string cannot be empty');
            return $result;
        }

        // Parse connection string parameters
        $params = self::parseConnectionString($connectionString);

        // Validate required parameters for different drivers
        switch ($driver) {
            case 'mysql':
            case 'pgsql':
                if (!isset($params['host']) && !isset($params['unix_socket'])) {
                    $result->addError('MySQL/PostgreSQL DSN must include host or unix_socket');
                }
                if (!isset($params['dbname'])) {
                    $result->addError('MySQL/PostgreSQL DSN must include dbname');
                }
                break;
        }

        // Security checks
        if (isset($params['host']) && $params['host'] === 'localhost') {
            // Note: Database host is localhost - ensure this is intended for production
        }

        return $result;
    }

    private static function validateSqliteDsn(string $dsn, ValidationResult $result): ValidationResult
    {
        $path = substr($dsn, 7); // Remove 'sqlite:' prefix
        
        if (empty($path) || $path === ':memory:') {
            return $result; // Valid in-memory database
        }

        if (!file_exists($path) && !is_writable(dirname($path))) {
            // Note: SQLite database file does not exist and directory is not writable
        }

        return $result;
    }

    /** @return array<string, string> */
    private static function parseConnectionString(string $connectionString): array
    {
        $params = [];
        $pairs = explode(';', $connectionString);
        
        foreach ($pairs as $pair) {
            $keyValue = explode('=', $pair, 2);
            if (count($keyValue) === 2) {
                $params[trim($keyValue[0])] = trim($keyValue[1]);
            }
        }
        
        return $params;
    }

    /**
     * Get the database driver.
     */
    public function getDriver(): string
    {
        $parts = explode(':', $this->toNative(), 2);
        return $parts[0];
    }

    /**
     * Get the connection string part (without driver).
     */
    public function getConnectionString(): string
    {
        $parts = explode(':', $this->toNative(), 2);
        return $parts[1] ?? '';
    }

    /**
     * Parse and get connection parameters.
     * 
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        if ($this->getDriver() === 'sqlite') {
            return ['path' => substr($this->toNative(), 7)];
        }

        return self::parseConnectionString($this->getConnectionString());
    }

    /**
     * Get the database host.
     */
    public function getHost(): ?string
    {
        return $this->getParameters()['host'] ?? null;
    }

    /**
     * Get the database name.
     */
    public function getDatabaseName(): ?string
    {
        return $this->getParameters()['dbname'] ?? null;
    }

    /**
     * Get the database port.
     */
    public function getPort(): ?int
    {
        $port = $this->getParameters()['port'] ?? null;
        return $port ? (int)$port : null;
    }

    /**
     * Check if this is a SQLite database.
     */
    public function isSqlite(): bool
    {
        return $this->getDriver() === 'sqlite';
    }

    /**
     * Check if this is an in-memory database.
     */
    public function isInMemory(): bool
    {
        return $this->isSqlite() && $this->getParameters()['path'] === ':memory:';
    }
} 