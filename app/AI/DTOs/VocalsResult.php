<?php

namespace App\AI\DTOs;

class VocalsResult
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $providerSlug,
        public readonly ?int $creditsUsed = null,
    ) {}
}
