<?php

namespace App\AI\DTOs;

enum ProviderStatus: string
{
    case Healthy = 'healthy';
    case Limited = 'limited';
    case RateLimited = 'rate_limited';
    case QuotaExhausted = 'quota_exhausted';
    case Error = 'error';
    case Disabled = 'disabled';
}
