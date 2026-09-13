<?php

namespace App\AI\DTOs;

class MusicRequest
{
    public function __construct(
        public readonly string $genre,
        public readonly string $mood,
        public readonly ?int $tempoBpm,
        public readonly array $instruments,
        public readonly ?int $durationSeconds,
        public readonly ?string $lyrics = null,
        public readonly ?string $description = null,
    ) {}
}
