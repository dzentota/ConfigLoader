<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader;

use dzentota\ConfigLoader\Source\EnvironmentSource;
use dzentota\ConfigLoader\Source\JsonFileSource;
use dzentota\ConfigLoader\Source\ArraySource;

/**
 * Factory for creating ConfigLoader instances with common configurations.
 * 
 * Following AppSec Manifesto Rule #0: Absolute Zero -
 * Provide secure defaults and minimize attack surface.
 */
class ConfigLoaderFactory
{
    /**
     * Create a ConfigLoader with environment variables only.
     * 
     * @param string $envPrefix Prefix for environment variables (e.g., 'APP_')
     * @param bool $strict Enable strict mode
     * @return ConfigLoader
     */
    public static function createFromEnvironment(string $envPrefix = '', bool $strict = true): ConfigLoader
    {
        $loader = new ConfigLoader($strict);
        $loader->addSource(new EnvironmentSource($envPrefix, 100));
        
        return $loader;
    }

    /**
     * Create a ConfigLoader with JSON file and environment variables.
     * Environment variables have higher priority and can override file values.
     * 
     * @param string $jsonFilePath Path to JSON configuration file
     * @param string $envPrefix Prefix for environment variables
     * @param bool $strict Enable strict mode
     * @param bool $fileRequired Whether the JSON file is required to exist
     * @return ConfigLoader
     */
    public static function createFromJsonAndEnv(
        string $jsonFilePath,
        string $envPrefix = '',
        bool $strict = true,
        bool $fileRequired = true
    ): ConfigLoader {
        $loader = new ConfigLoader($strict);
        
        // Add JSON file source with lower priority
        $loader->addSource(new JsonFileSource($jsonFilePath, 50, $fileRequired));
        
        // Add environment source with higher priority (overrides file)
        $loader->addSource(new EnvironmentSource($envPrefix, 100));
        
        return $loader;
    }

    /**
     * Create a ConfigLoader with multiple JSON files and environment variables.
     * Files are loaded in order, with later files overriding earlier ones.
     * Environment variables have the highest priority.
     * 
     * @param string[] $jsonFilePaths Array of JSON file paths
     * @param string $envPrefix Prefix for environment variables
     * @param bool $strict Enable strict mode
     * @param bool $filesRequired Whether all JSON files are required to exist
     * @return ConfigLoader
     */
    public static function createFromMultipleFiles(
        array $jsonFilePaths,
        string $envPrefix = '',
        bool $strict = true,
        bool $filesRequired = true
    ): ConfigLoader {
        $loader = new ConfigLoader($strict);
        
        // Add JSON files with increasing priority
        $basePriority = 10;
        foreach ($jsonFilePaths as $index => $filePath) {
            $priority = $basePriority + ($index * 10);
            $loader->addSource(new JsonFileSource($filePath, $priority, $filesRequired));
        }
        
        // Add environment source with highest priority
        $loader->addSource(new EnvironmentSource($envPrefix, 100));
        
        return $loader;
    }

    /**
     * Create a ConfigLoader with array data and environment variables.
     * Useful for testing or when you have configuration data already loaded.
     * 
     * @param array<string, mixed> $configData Initial configuration data
     * @param string $envPrefix Prefix for environment variables
     * @param bool $strict Enable strict mode
     * @return ConfigLoader
     */
    public static function createFromArrayAndEnv(
        array $configData,
        string $envPrefix = '',
        bool $strict = true
    ): ConfigLoader {
        $loader = new ConfigLoader($strict);
        
        // Add array source with lower priority
        $loader->addSource(new ArraySource($configData, 50, 'Initial Config'));
        
        // Add environment source with higher priority
        $loader->addSource(new EnvironmentSource($envPrefix, 100));
        
        return $loader;
    }

    /**
     * Create a ConfigLoader with defaults, JSON file, and environment variables.
     * This is the most common setup: defaults < file < environment.
     * 
     * @param array<string, mixed> $defaults Default configuration values
     * @param string $jsonFilePath Path to JSON configuration file
     * @param string $envPrefix Prefix for environment variables
     * @param bool $strict Enable strict mode
     * @param bool $fileRequired Whether the JSON file is required to exist
     * @return ConfigLoader
     */
    public static function createLayered(
        array $defaults,
        string $jsonFilePath,
        string $envPrefix = '',
        bool $strict = true,
        bool $fileRequired = false
    ): ConfigLoader {
        $loader = new ConfigLoader($strict);
        
        // Add defaults with lowest priority
        $loader->addSource(new ArraySource($defaults, 10, 'Defaults'));
        
        // Add JSON file with medium priority
        $loader->addSource(new JsonFileSource($jsonFilePath, 50, $fileRequired));
        
        // Add environment with highest priority
        $loader->addSource(new EnvironmentSource($envPrefix, 100));
        
        return $loader;
    }

    /**
     * Create a ConfigLoader that follows the twelve-factor app methodology.
     * Uses environment variables for all configuration.
     * 
     * @param string $appName Application name used as environment variable prefix
     * @param bool $strict Enable strict mode
     * @return ConfigLoader
     */
    public static function createTwelveFactor(string $appName, bool $strict = true): ConfigLoader
    {
        $envPrefix = strtoupper($appName) . '_';
        return self::createFromEnvironment($envPrefix, $strict);
    }

    /**
     * Create a ConfigLoader optimized for Docker deployments.
     * Supports both environment variables and Docker secrets mounted as files.
     * 
     * @param string $envPrefix Prefix for environment variables
     * @param string $secretsPath Path where Docker secrets are mounted
     * @param bool $strict Enable strict mode
     * @return ConfigLoader
     */
    public static function createForDocker(
        string $envPrefix = '',
        string $secretsPath = '/run/secrets',
        bool $strict = true
    ): ConfigLoader {
        $loader = new ConfigLoader($strict);
        
        // Add environment variables with highest priority
        $loader->addSource(new EnvironmentSource($envPrefix, 100));
        
        // Add Docker secrets if the directory exists
        if (is_dir($secretsPath)) {
            $secretFiles = glob($secretsPath . '/*');
            if ($secretFiles) {
                foreach ($secretFiles as $index => $secretFile) {
                    if (is_file($secretFile) && is_readable($secretFile)) {
                        $secretName = basename($secretFile);
                        $secretContent = trim(file_get_contents($secretFile) ?: '');
                        $loader->addSource(new ArraySource([$secretName => $secretContent], 50 + $index, "Secret: $secretName"));
                    }
                }
            }
        }
        
        return $loader;
    }

    /**
     * Create a minimal ConfigLoader for testing purposes.
     * Only uses array data, no external sources.
     * 
     * @param array<string, mixed> $testData Test configuration data
     * @return ConfigLoader
     */
    public static function createForTesting(array $testData): ConfigLoader
    {
        $loader = new ConfigLoader(true); // Always strict in tests
        $loader->addSource(new ArraySource($testData, 100, 'Test Data'));
        
        return $loader;
    }
} 