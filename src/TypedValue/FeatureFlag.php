<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\TypedValue;

use dzentota\TypedValue\Typed;
use dzentota\TypedValue\TypedValue;
use dzentota\TypedValue\ValidationResult;

/**
 * Feature flag value object with smart boolean parsing.
 * 
 * Following AppSec Manifesto Rule #3: Forget-me-not -
 * Preserving data validity by declaring custom types instead of 
 * using primitive types for unstructured data.
 */
class FeatureFlag implements Typed
{
    use TypedValue;

    private const TRUE_VALUES = ['true', '1', 'yes', 'on', 'enabled', 'enable'];
    private const FALSE_VALUES = ['false', '0', 'no', 'off', 'disabled', 'disable'];

    public static function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();

        if (is_bool($value)) {
            return $result; // Already a boolean, valid
        }

        if (is_numeric($value)) {
            $numValue = (float)$value;
            if ($numValue !== 0.0 && $numValue !== 1.0) {
                $result->addError('Numeric feature flag must be 0 or 1');
            }
            return $result;
        }

        if (!is_string($value)) {
            $result->addError('Feature flag must be boolean, numeric, or string');
            return $result;
        }

        $normalized = strtolower(trim($value));
        
        if (empty($normalized)) {
            $result->addError('Feature flag cannot be empty');
            return $result;
        }

        $allValidValues = array_merge(self::TRUE_VALUES, self::FALSE_VALUES);
        
        if (!in_array($normalized, $allValidValues, true)) {
            $result->addError(sprintf(
                'Invalid feature flag value "%s". Valid values: %s',
                $value,
                implode(', ', $allValidValues)
            ));
        }

        return $result;
    }

    /**
     * Get the boolean value of the feature flag.
     */
    public function isEnabled(): bool
    {
        $value = $this->toNative();

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float)$value !== 0.0;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, self::TRUE_VALUES, true);
    }

    /**
     * Alias for isEnabled() for better readability.
     */
    public function toBool(): bool
    {
        return $this->isEnabled();
    }

    /**
     * Get the opposite of the current flag value.
     */
    public function isDisabled(): bool
    {
        return !$this->isEnabled();
    }

    /**
     * Get a normalized string representation.
     */
    public function toString(): string
    {
        return $this->isEnabled() ? 'true' : 'false';
    }
} 