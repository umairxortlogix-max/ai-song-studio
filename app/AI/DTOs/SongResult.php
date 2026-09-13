<?php

namespace App\AI\DTOs;

class SongResult
{
    public function __construct(
        public readonly ?string $lyrics,
        public readonly ?string $instrumentalPath,
        public readonly ?string $vocalsPath,
        public readonly ?string $finalPath,
        public readonly string $providerSlug,
        public readonly ?int $tokensUsed = null,
        public readonly ?int $creditsUsed = null,
        public readonly ?int $durationSeconds = null,
    ) {}
}
