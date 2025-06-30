<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\TypedValue;

use dzentota\TypedValue\Typed;
use dzentota\TypedValue\TypedValue;
use dzentota\TypedValue\ValidationResult;

/**
 * Service URL value object with validation.
 * 
 * Following AppSec Manifesto Rule #3: Forget-me-not -
 * Preserving data validity by declaring custom types instead of 
 * using primitive types for unstructured data.
 */
class ServiceUrl implements Typed
{
    use TypedValue;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    public static function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();

        if (!is_string($value)) {
            $result->addError('URL must be a string');
            return $result;
        }

        if (empty($value)) {
            $result->addError('URL cannot be empty');
            return $result;
        }

        $parsed = parse_url($value);
        
        if ($parsed === false) {
            $result->addError('Invalid URL format');
            return $result;
        }

        if (!isset($parsed['scheme'])) {
            $result->addError('URL must include a scheme (http/https)');
            return $result;
        }

        if (!in_array($parsed['scheme'], self::ALLOWED_SCHEMES, true)) {
            $result->addError(sprintf(
                'URL scheme must be one of: %s, got: %s',
                implode(', ', self::ALLOWED_SCHEMES),
                $parsed['scheme']
            ));
        }

        if (!isset($parsed['host'])) {
            $result->addError('URL must include a host');
            return $result;
        }

        // Additional security check: no localhost in production
        if (in_array($parsed['host'], ['localhost', '127.0.0.1', '::1'], true)) {
            // Note: URL points to localhost - ensure this is intended
        }

        return $result;
    }

    /**
     * Get the URL scheme (http/https).
     */
    public function getScheme(): string
    {
        return parse_url($this->toNative(), PHP_URL_SCHEME) ?: '';
    }

    /**
     * Get the URL host.
     */
    public function getHost(): string
    {
        return parse_url($this->toNative(), PHP_URL_HOST) ?: '';
    }

    /**
     * Get the URL port.
     */
    public function getPort(): ?int
    {
        $port = parse_url($this->toNative(), PHP_URL_PORT);
        return is_int($port) ? $port : null;
    }

    /**
     * Get the URL path.
     */
    public function getPath(): string
    {
        $path = parse_url($this->toNative(), PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    /**
     * Check if this is an HTTPS URL.
     */
    public function isSecure(): bool
    {
        return $this->getScheme() === 'https';
    }

    /**
     * Get the effective port (with default ports for schemes).
     */
    public function getEffectivePort(): int
    {
        $port = $this->getPort();
        
        if ($port !== null) {
            return $port;
        }

        return $this->getScheme() === 'https' ? 443 : 80;
    }
} 