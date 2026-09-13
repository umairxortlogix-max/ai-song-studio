<?php

namespace App\AI\DTOs;

class MusicResult
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $providerSlug,
        public readonly ?int $durationSeconds = null,
        public readonly ?int $creditsUsed = null,
    ) {}
}
