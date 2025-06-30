<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader;

use dzentota\TypedValue\Typed;

/**
 * Interface for parsing configuration values into TypedValue objects.
 * 
 * Following AppSec Manifesto Rule #2: The Parser's Prerogative -
 * Parse, not validate, input data as close to the source, 
 * quickly failing if data does not conform to expectations.
 */
interface ParserInterface
{
    /**
     * Parse a raw configuration value into a TypedValue object.
     * 
     * @param mixed $value Raw configuration value
     * @param string $typedValueClass The TypedValue class to parse into
     * @return Typed The parsed TypedValue object
     * @throws \dzentota\ConfigLoader\Exception\ValidationException
     */
    public function parse($value, string $typedValueClass): Typed;

    /**
     * Check if this parser can handle the given TypedValue class.
     */
    public function canParse(string $typedValueClass): bool;

    /**
     * Get the priority of this parser (higher number = higher priority).
     * Parsers with higher priority will be tried first.
     */
    public function getPriority(): int;
} 