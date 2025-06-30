<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Source;

use dzentota\ConfigLoader\SourceInterface;
use dzentota\ConfigLoader\Exception\SourceException;

/**
 * In-memory array configuration source.
 * 
 * Following AppSec Manifesto Rule #4: Declaration of Sources Rights -
 * All sources are born at the same architectural level and should be treated equally.
 */
class ArraySource implements SourceInterface
{
    /** @var array<string, mixed> */
    private array $data;
    private int $priority;
    private string $name;

    /**
     * @param array<string, mixed> $data Configuration data
     * @param int $priority Source priority (higher = more important)
     * @param string $name Human-readable name for this source
     */
    public function __construct(array $data, int $priority = 10, string $name = 'Array Source')
    {
        $this->data = $data;
        $this->priority = $priority;
        $this->name = $name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function load(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function get(string $key)
    {
        if (!isset($this->data[$key])) {
            throw new SourceException(
                $key,
                'array',
                sprintf('Configuration key "%s" not found in array source', $key)
            );
        }
        
        return $this->data[$key];
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Update the configuration data.
     * 
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Merge additional data into the existing configuration.
     * 
     * @param array<string, mixed> $data
     */
    public function mergeData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Set a specific configuration value.
     * 
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Remove a configuration key.
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Get all configuration keys.
     * 
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Check if the source is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Get the number of configuration items.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Clear all configuration data.
     */
    public function clear(): void
    {
        $this->data = [];
    }
} 