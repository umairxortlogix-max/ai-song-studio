<?php

namespace App\AI\Exceptions;

/**
 * Thrown for timeouts, 5xx errors, or connection failures that are
 * likely transient. The router will retry a limited number of times
 * with exponential backoff before moving to the next provider.
 */
class TemporaryProviderException extends ProviderException
{
    public function __construct(string $providerSlug, string $message = 'Provider temporarily unavailable')
    {
        parent::__construct($message, $providerSlug, isRetryableLater: true);
    }
}
