<?php

namespace App\AI\Exceptions;

/**
 * Thrown when a provider's API key is missing, invalid, or revoked.
 * Treated as a hard failure for this provider (needs admin attention),
 * but the router still moves on to the next provider automatically.
 */
class ProviderAuthException extends ProviderException
{
    public function __construct(string $providerSlug, string $message = 'Provider authentication failed')
    {
        parent::__construct($message, $providerSlug, isRetryableLater: false);
    }
}
