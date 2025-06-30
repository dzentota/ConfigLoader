<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Exception;

/**
 * Exception thrown when configuration value validation fails.
 * 
 * Following AppSec Manifesto Rule #2: The Parser's Prerogative -
 * Parse, don't validate. Fail fast when data doesn't conform.
 */
class ValidationException extends ConfigLoaderException
{
    private string $configKey;
    private mixed $rawValue;

    public function __construct(string $configKey, mixed $rawValue, string $message, ?\Throwable $previous = null)
    {
        $this->configKey = $configKey;
        $this->rawValue = $rawValue;
        
        parent::__construct(
            sprintf(
                'Validation failed for config key "%s" with value "%s": %s',
                $configKey,
                is_scalar($rawValue) ? (string)$rawValue : gettype($rawValue),
                $message
            ),
            0,
            $previous
        );
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function getRawValue(): mixed
    {
        return $this->rawValue;
    }
} 