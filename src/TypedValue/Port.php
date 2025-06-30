<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\TypedValue;

use dzentota\TypedValue\Typed;
use dzentota\TypedValue\TypedValue;
use dzentota\TypedValue\ValidationResult;

/**
 * Port number value object (1-65535).
 * 
 * Following AppSec Manifesto Rule #3: Forget-me-not -
 * Preserving data validity by declaring custom types instead of 
 * using primitive types for unstructured data.
 */
class Port implements Typed
{
    use TypedValue;

    public const MIN_PORT = 1;
    public const MAX_PORT = 65535;

    public static function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();

        if (!is_numeric($value)) {
            $result->addError('Port must be numeric');
            return $result;
        }

        $port = (int)$value;

        if ($port < self::MIN_PORT || $port > self::MAX_PORT) {
            $result->addError(sprintf(
                'Port must be between %d and %d, got %d',
                self::MIN_PORT,
                self::MAX_PORT,
                $port
            ));
        }

        return $result;
    }

    /**
     * Get the port number as integer.
     */
    public function toInt(): int
    {
        return (int)$this->toNative();
    }

    /**
     * Check if this is a well-known port (1-1023).
     */
    public function isWellKnown(): bool
    {
        return $this->toInt() <= 1023;
    }

    /**
     * Check if this is a registered port (1024-49151).
     */
    public function isRegistered(): bool
    {
        $port = $this->toInt();
        return $port >= 1024 && $port <= 49151;
    }

    /**
     * Check if this is a dynamic/private port (49152-65535).
     */
    public function isDynamic(): bool
    {
        return $this->toInt() >= 49152;
    }
} 