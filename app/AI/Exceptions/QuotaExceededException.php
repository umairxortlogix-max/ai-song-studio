<?php

namespace App\AI\Exceptions;

/**
 * Thrown when a provider's daily/monthly free-tier quota is exhausted.
 * The router marks this provider "quota_exhausted" and will not retry
 * it again until the next reset window (handled by the scheduler).
 */
class QuotaExceededException extends ProviderException
{
    public function __construct(string $providerSlug, string $message = 'Provider quota exceeded')
    {
        parent::__construct($message, $providerSlug, isRetryableLater: false);
    }
}
