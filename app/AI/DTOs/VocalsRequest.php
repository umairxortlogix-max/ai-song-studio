<?php

namespace App\AI\DTOs;

class VocalsRequest
{
    public function __construct(
        public readonly string $lyrics,
        public readonly string $vocalType,
        public readonly string $language,
        public readonly ?string $voiceStyle = null,
        public readonly ?string $instrumentalPath = null,
    ) {}
}
