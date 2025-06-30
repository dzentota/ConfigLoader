<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Exception;

use Exception;

/**
 * Base exception for all ConfigLoader related errors.
 * 
 * Following AppSec Manifesto Rule #8: The Vigilant Eye - 
 * Log security-relevant events and failures for monitoring.
 */
class ConfigLoaderException extends Exception
{
} 