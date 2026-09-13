<?php

namespace App\AI\Exceptions;

use Exception;

/**
 * Base class for all provider-related exceptions. The router catches
 * this family to decide whether to skip to the next provider.
 */
abstract class ProviderException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $providerSlug,
        public readonly bool $isRetryableLater = false,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
