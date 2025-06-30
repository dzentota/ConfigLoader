<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Source;

use dzentota\ConfigLoader\SourceInterface;
use dzentota\ConfigLoader\Exception\SourceException;

/**
 * JSON file configuration source.
 * 
 * Following AppSec Manifesto Rule #4: Declaration of Sources Rights -
 * All sources are born at the same architectural level and should be treated equally.
 * 
 * Following AppSec Manifesto Rule #0: Absolute Zero -
 * Only load files that exist and are readable to minimize attack surface.
 */
class JsonFileSource implements SourceInterface
{
    private string $filePath;
    private int $priority;
    private ?array $cachedData = null;
    private bool $required;

    public function __construct(string $filePath, int $priority = 50, bool $required = true)
    {
        $this->filePath = $filePath;
        $this->priority = $priority;
        $this->required = $required;
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

        if (!file_exists($this->filePath)) {
            if ($this->required) {
                throw new SourceException(
                    $this->filePath,
                    'json_file',
                    'Required JSON configuration file does not exist'
                );
            }
            
            $this->cachedData = [];
            return $this->cachedData;
        }

        if (!is_readable($this->filePath)) {
            throw new SourceException(
                $this->filePath,
                'json_file',
                'JSON configuration file is not readable'
            );
        }

        $content = file_get_contents($this->filePath);
        
        if ($content === false) {
            throw new SourceException(
                $this->filePath,
                'json_file',
                'Failed to read JSON configuration file'
            );
        }

        if (empty($content)) {
            $this->cachedData = [];
            return $this->cachedData;
        }

        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SourceException(
                $this->filePath,
                'json_file',
                sprintf(
                    'Invalid JSON in configuration file: %s',
                    json_last_error_msg()
                )
            );
        }

        if (!is_array($data)) {
            throw new SourceException(
                $this->filePath,
                'json_file',
                'JSON configuration file must contain an object/array at root level'
            );
        }

        $this->cachedData = $this->flattenArray($data);
        return $this->cachedData;
    }

    public function has(string $key): bool
    {
        $data = $this->load();
        return isset($data[$key]);
    }

    public function get(string $key)
    {
        $data = $this->load();
        
        if (!isset($data[$key])) {
            throw new SourceException(
                $this->filePath,
                'json_file',
                sprintf('Configuration key "%s" not found in JSON file', $key)
            );
        }
        
        return $data[$key];
    }

    public function getName(): string
    {
        return sprintf('JSON File (%s)', $this->filePath);
    }

    /**
     * Flatten nested array using dot notation.
     * 
     * Example: ['db' => ['host' => 'localhost']] becomes ['db.host' => 'localhost']
     * 
     * @param array<string, mixed> $array
     * @param string $prefix
     * @return array<string, mixed>
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            
            if (is_array($value) && !empty($value) && array_keys($value) !== range(0, count($value) - 1)) {
                // Recursive call for associative arrays (not numeric indexed arrays)
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Clear the cached data to force reload on next access.
     */
    public function clearCache(): void
    {
        $this->cachedData = null;
    }

    /**
     * Get the file path.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Check if the file is required.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Check if the file exists.
     */
    public function fileExists(): bool
    {
        return file_exists($this->filePath);
    }

    /**
     * Get the file modification time.
     */
    public function getFileModificationTime(): int
    {
        if (!$this->fileExists()) {
            return 0;
        }
        
        $mtime = filemtime($this->filePath);
        return $mtime !== false ? $mtime : 0;
    }
} 