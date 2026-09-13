<?php

namespace App\AI\Exceptions;

/**
 * Thrown when a provider returns HTTP 429 or an equivalent
 * "too many requests" response. The provider is temporarily
 * limited but may recover soon (exponential backoff applies).
 */
class RateLimitException extends ProviderException
{
    public function __construct(string $providerSlug, string $message = 'Rate limit exceeded', public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message, $providerSlug, isRetryableLater: true);
    }
}
