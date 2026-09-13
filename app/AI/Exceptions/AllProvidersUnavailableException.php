<?php

namespace App\AI\Exceptions;

use Exception;

/**
 * Thrown by AiRouterService when every configured provider, in
 * priority order, has failed, is rate-limited, or has exhausted
 * its quota. The controller/job catches this and shows the user
 * a friendly message without exposing internal errors.
 */
class AllProvidersUnavailableException extends Exception
{
    public function __construct(public readonly array $attemptedProviders = [])
    {
        parent::__construct('All available AI generation providers are currently unavailable.');
    }
}
