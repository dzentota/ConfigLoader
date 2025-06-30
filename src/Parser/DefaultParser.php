<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Parser;

use dzentota\ConfigLoader\ParserInterface;
use dzentota\ConfigLoader\Exception\ValidationException;
use dzentota\TypedValue\Typed;
use dzentota\TypedValue\ValidationResult;

/**
 * Default parser that works with any TypedValue class.
 * 
 * Following AppSec Manifesto Rule #2: The Parser's Prerogative -
 * Parse, not validate, input data as close to the source, 
 * quickly failing if data does not conform to expectations.
 */
class DefaultParser implements ParserInterface
{
    private int $priority;

    public function __construct(int $priority = 0)
    {
        $this->priority = $priority;
    }

    public function parse($value, string $typedValueClass): Typed
    {
        if (!$this->canParse($typedValueClass)) {
            throw new ValidationException(
                'unknown',
                $value,
                sprintf('Class "%s" does not implement Typed interface', $typedValueClass)
            );
        }

        try {
            // Use the TypedValue's own validation and creation mechanism
            return $typedValueClass::fromNative($value);
        } catch (\Exception $e) {
            // If the TypedValue class throws an exception, wrap it in our ValidationException
            throw new ValidationException(
                'unknown',
                $value,
                sprintf('Failed to create %s: %s', $typedValueClass, $e->getMessage()),
                $e
            );
        }
    }

    public function canParse(string $typedValueClass): bool
    {
        // Check if the class exists and implements the Typed interface
        if (!class_exists($typedValueClass)) {
            return false;
        }

        $reflection = new \ReflectionClass($typedValueClass);
        return $reflection->implementsInterface(Typed::class);
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Validate a value using a TypedValue class without creating the object.
     * 
     * @param mixed $value
     * @param string $typedValueClass
     * @return ValidationResult
     */
    public function validate($value, string $typedValueClass): ValidationResult
    {
        if (!$this->canParse($typedValueClass)) {
            $result = new ValidationResult();
            $result->addError(sprintf('Class "%s" does not implement Typed interface', $typedValueClass));
            return $result;
        }

        if (!method_exists($typedValueClass, 'validate')) {
            $result = new ValidationResult();
            $result->addError(sprintf('Class "%s" does not have a validate method', $typedValueClass));
            return $result;
        }

        try {
            return $typedValueClass::validate($value);
        } catch (\Exception $e) {
            $result = new ValidationResult();
            $result->addError(sprintf('Validation failed: %s', $e->getMessage()));
            return $result;
        }
    }

    /**
     * Check if a value would be valid for a given TypedValue class.
     * 
     * @param mixed $value
     * @param string $typedValueClass
     * @return bool
     */
    public function isValid($value, string $typedValueClass): bool
    {
        $result = $this->validate($value, $typedValueClass);
        return $result->success();
    }
} 