<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader;

use dzentota\ConfigLoader\Exception\ConfigLoaderException;
use dzentota\ConfigLoader\Exception\ValidationException;
use dzentota\ConfigLoader\Exception\SourceException;
use dzentota\ConfigLoader\Parser\DefaultParser;
use dzentota\TypedValue\Typed;

/**
 * Main configuration loader that coordinates sources and parsers.
 * 
 * Following AppSec Manifesto principles:
 * - Rule #2: Parse, don't validate - immediate parsing into TypedValue objects
 * - Rule #3: Forget-me-not - preserve validity through typed objects
 * - Rule #4: Declaration of Sources Rights - uniform handling of all sources
 * - Rule #8: The Vigilant Eye - comprehensive logging of failures
 */
class ConfigLoader
{
    /** @var SourceInterface[] */
    private array $sources = [];
    
    /** @var ParserInterface[] */
    private array $parsers = [];
    
    /** @var array<string, mixed>|null */
    private ?array $cachedConfig = null;
    private bool $strict = true;

    public function __construct(bool $strict = true)
    {
        $this->strict = $strict;
        $this->addParser(new DefaultParser());
    }

    /**
     * Add a configuration source.
     */
    public function addSource(SourceInterface $source): self
    {
        $this->sources[] = $source;
        $this->clearCache();
        return $this;
    }

    /**
     * Add a parser for specific TypedValue classes.
     */
    public function addParser(ParserInterface $parser): self
    {
        $this->parsers[] = $parser;
        // Sort parsers by priority (highest first)
        usort($this->parsers, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
        return $this;
    }

    /**
     * Get a configuration value parsed into a TypedValue object.
     * 
     * Following AppSec Manifesto Rule #2: Parse, don't validate -
     * Configuration values are immediately parsed into valid TypedValue objects.
     * 
     * @param string $key Configuration key
     * @param string $typedValueClass TypedValue class to parse into
     * @param mixed|null $default Default value if key not found (only used in non-strict mode)
     * @return Typed
     * @throws ConfigLoaderException
     */
    public function get(string $key, string $typedValueClass, mixed $default = null): Typed
    {
        $rawValue = $this->getRaw($key, $default);
        
        if ($rawValue === $default && $default !== null) {
            // If we're using a default value, try to parse it as well
            return $this->parseValue($rawValue, $typedValueClass, $key);
        }
        
        return $this->parseValue($rawValue, $typedValueClass, $key);
    }

    /**
     * Get a raw configuration value without parsing.
     * 
     * @param string $key Configuration key
     * @param mixed|null $default Default value if key not found
     * @return mixed
     * @throws ConfigLoaderException
     */
    public function getRaw(string $key, mixed $default = null)
    {
        $config = $this->loadConfig();
        
        if (!isset($config[$key])) {
            if ($this->strict && $default === null) {
                throw new ConfigLoaderException(
                    sprintf('Configuration key "%s" not found and no default provided', $key)
                );
            }
            return $default;
        }
        
        return $config[$key];
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        $config = $this->loadConfig();
        return isset($config[$key]);
    }

    /**
     * Get all configuration keys.
     * 
     * @return string[]
     */
    public function getKeys(): array
    {
        $config = $this->loadConfig();
        return array_keys($config);
    }

    /**
     * Get all raw configuration data.
     * 
     * @return array<string, mixed>
     */
    public function getAllRaw(): array
    {
        return $this->loadConfig();
    }

    /**
     * Validate a configuration value without retrieving it.
     * 
     * @param string $key Configuration key
     * @param string $typedValueClass TypedValue class to validate against
     * @return bool
     */
    public function isValid(string $key, string $typedValueClass): bool
    {
        if (!$this->has($key)) {
            return false;
        }
        
        $rawValue = $this->getRaw($key);
        
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($typedValueClass)) {
                if (method_exists($parser, 'isValid')) {
                    return $parser->isValid($rawValue, $typedValueClass);
                }
                
                // Fallback: try to parse and catch exceptions
                try {
                    $parser->parse($rawValue, $typedValueClass);
                    return true;
                } catch (ValidationException $e) {
                    return false;
                }
            }
        }
        
        return false;
    }

    /**
     * Load and merge configuration from all sources.
     * 
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        if ($this->cachedConfig !== null) {
            return $this->cachedConfig;
        }
        
        // Sort sources by priority (lowest first, so higher priority sources override)
        $sortedSources = $this->sources;
        usort($sortedSources, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
        
        $config = [];
        
        foreach ($sortedSources as $source) {
            try {
                $sourceData = $source->load();
                $config = array_merge($config, $sourceData);
            } catch (SourceException $e) {
                if ($this->strict) {
                    throw $e;
                }
                // In non-strict mode, log the error but continue
                error_log(sprintf('ConfigLoader: Failed to load from source "%s": %s', $source->getName(), $e->getMessage()));
            }
        }
        
        $this->cachedConfig = $config;
        return $config;
    }

    /**
     * Parse a raw value into a TypedValue object.
     * 
     * @param mixed $value
     * @param string $typedValueClass
     * @param string $key
     * @return Typed
     * @throws ValidationException
     */
    private function parseValue($value, string $typedValueClass, string $key): Typed
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($typedValueClass)) {
                try {
                    return $parser->parse($value, $typedValueClass);
                } catch (ValidationException $e) {
                    // Update the exception with the actual config key
                    throw new ValidationException($key, $value, $e->getMessage(), $e);
                }
            }
        }
        
        throw new ValidationException(
            $key,
            $value,
            sprintf('No parser found for TypedValue class "%s"', $typedValueClass)
        );
    }

    /**
     * Clear the configuration cache to force reload.
     */
    public function clearCache(): void
    {
        $this->cachedConfig = null;
        
        // Also clear source caches if they support it
        foreach ($this->sources as $source) {
            if (method_exists($source, 'clearCache')) {
                $source->clearCache();
            }
        }
    }

    /**
     * Set strict mode.
     * 
     * In strict mode:
     * - Missing configuration keys throw exceptions
     * - Source loading failures throw exceptions
     * 
     * In non-strict mode:
     * - Missing keys return defaults
     * - Source failures are logged but don't stop processing
     */
    public function setStrict(bool $strict): self
    {
        $this->strict = $strict;
        return $this;
    }

    /**
     * Check if strict mode is enabled.
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * Get information about all registered sources.
     * 
     * @return array<int, array{name: string, priority: int, type: string}>
     */
    public function getSourceInfo(): array
    {
        return array_map(function(SourceInterface $source) {
            return [
                'name' => $source->getName(),
                'priority' => $source->getPriority(),
                'type' => get_class($source)
            ];
        }, $this->sources);
    }

    /**
     * Get information about all registered parsers.
     * 
     * @return array<int, array{priority: int, type: string}>
     */
    public function getParserInfo(): array
    {
        return array_map(function(ParserInterface $parser) {
            return [
                'priority' => $parser->getPriority(),
                'type' => get_class($parser)
            ];
        }, $this->parsers);
    }
} 