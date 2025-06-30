<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Exception;

/**
 * Exception thrown when a configuration source cannot be read or accessed.
 * 
 * Following AppSec Manifesto Rule #4: Declaration of Sources Rights -
 * All sources should be treated equally and processed uniformly.
 */
class SourceException extends ConfigLoaderException
{
    private string $sourceName;
    private string $sourceType;

    public function __construct(string $sourceName, string $sourceType, string $message, ?\Throwable $previous = null)
    {
        $this->sourceName = $sourceName;
        $this->sourceType = $sourceType;
        
        parent::__construct(
            sprintf(
                'Failed to load from %s source "%s": %s',
                $sourceType,
                $sourceName,
                $message
            ),
            0,
            $previous
        );
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }
} 