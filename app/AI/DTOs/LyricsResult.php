<?php

namespace App\AI\DTOs;

class LyricsResult
{
    public function __construct(
        public readonly string $lyrics,
        public readonly string $providerSlug,
        public readonly ?int $tokensUsed = null,
    ) {}
}
