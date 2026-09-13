<?php

namespace App\AI\DTOs;

class LyricsRequest
{
    public function __construct(
        public readonly string $theme,
        public readonly string $language,
        public readonly string $genre,
        public readonly string $mood,
        public readonly ?string $title = null,
        public readonly ?int $verses = 3,
    ) {}
}
