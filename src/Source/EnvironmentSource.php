<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Source;

use dzentota\ConfigLoader\SourceInterface;
use dzentota\ConfigLoader\Exception\SourceException;

/**
 * Environment variables configuration source.
 * 
 * Following AppSec Manifesto Rule #4: Declaration of Sources Rights -
 * All sources are born at the same architectural level and should be treated equally.
 */
class EnvironmentSource implements SourceInterface
{
    private string $prefix;
    private int $priority;
    private ?array $cachedData = null;

    public function __construct(string $prefix = '', int $priority = 100)
    {
        $this->prefix = $prefix;
        $this->priority = $priority;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function load(): array
    {
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        $data = [];
        
        // Get all environment variables using both $_ENV and getenv()
        $envVars = array_merge($_ENV, $this->getAllEnvVars());

        foreach ($envVars as $key => $value) {
            if ($this->matchesPrefix($key)) {
                $configKey = $this->removePrefix($key);
                $data[$configKey] = $value;
            }
        }

        $this->cachedData = $data;
        return $data;
    }

    public function has(string $key): bool
    {
        $envKey = $this->addPrefix($key);
        
        // Check both $_ENV and getenv() for maximum compatibility
        return isset($_ENV[$envKey]) || getenv($envKey) !== false;
    }

    public function get(string $key)
    {
        $envKey = $this->addPrefix($key);
        
        // Try $_ENV first, then getenv()
        if (isset($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }
        
        $value = getenv($envKey);
        if ($value !== false) {
            return $value;
        }
        
        throw new SourceException(
            $envKey,
            'environment',
            sprintf('Environment variable "%s" not found', $envKey)
        );
    }

    public function getName(): string
    {
        return sprintf('Environment Variables%s', $this->prefix ? " (prefix: {$this->prefix})" : '');
    }

    /**
     * Get all environment variables using getenv() for variables not in $_ENV.
     * 
     * @return array<string, string>
     */
    private function getAllEnvVars(): array
    {
        $envVars = [];
        
        // Try to get variables from $_SERVER (includes environment variables)
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && getenv($key) !== false) {
                $envVars[$key] = $value;
            }
        }
        
        return $envVars;
    }

    private function matchesPrefix(string $key): bool
    {
        if (empty($this->prefix)) {
            return true; // No prefix means match all
        }
        
        return str_starts_with($key, $this->prefix);
    }

    private function removePrefix(string $key): string
    {
        if (empty($this->prefix)) {
            return $key;
        }
        
        return substr($key, strlen($this->prefix));
    }

    private function addPrefix(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Clear the cached data to force reload on next access.
     */
    public function clearCache(): void
    {
        $this->cachedData = null;
    }

    /**
     * Set the prefix for environment variable filtering.
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
        $this->clearCache(); // Clear cache when prefix changes
    }

    /**
     * Get the current prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
} 