<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader;

/**
 * Interface for configuration sources.
 * 
 * Following AppSec Manifesto Rule #4: Declaration of Sources Rights -
 * All sources are born at the same architectural level and should be treated equally.
 * Data originating from different sources must undergo identical processing.
 */
interface SourceInterface
{
    /**
     * Get the priority of this source (higher number = higher priority).
     * Sources with higher priority will override values from lower priority sources.
     */
    public function getPriority(): int;

    /**
     * Load configuration data from this source.
     * 
     * @return array<string, mixed> Configuration key-value pairs
     * @throws \dzentota\ConfigLoader\Exception\SourceException
     */
    public function load(): array;

    /**
     * Check if this source has a specific configuration key.
     */
    public function has(string $key): bool;

    /**
     * Get a specific configuration value from this source.
     * 
     * @return mixed
     * @throws \dzentota\ConfigLoader\Exception\SourceException
     */
    public function get(string $key);

    /**
     * Get a human-readable name for this source (for debugging/logging).
     */
    public function getName(): string;
} 