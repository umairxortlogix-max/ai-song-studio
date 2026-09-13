<?php

namespace App\AI\DTOs;

/**
 * Provider-independent, structured description of what to generate.
 * Built by App\AI\Services\MusicPromptBuilder from the user's form input.
 */
class SongRequest
{
    public function __construct(
        public readonly ?int $songId,
        public readonly string $title,
        public readonly ?string $lyrics,
        public readonly string $language,
        public readonly string $genre,
        public readonly string $mood,
        public readonly string $vocalType,       // male|female|duet|instrumental
        public readonly ?int $tempoBpm,
        public readonly array $instruments = [],
        public readonly ?int $durationSeconds = null,
        public readonly ?string $description = null,
        public readonly ?string $voiceStyle = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            songId: $data['song_id'] ?? null,
            title: $data['title'],
            lyrics: $data['lyrics'] ?? null,
            language: $data['language'],
            genre: $data['genre'],
            mood: $data['mood'],
            vocalType: $data['vocal_type'],
            tempoBpm: $data['tempo_bpm'] ?? null,
            instruments: $data['instruments'] ?? [],
            durationSeconds: $data['duration_seconds'] ?? null,
            description: $data['description'] ?? null,
            voiceStyle: $data['voice_style'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'song_id' => $this->songId,
            'title' => $this->title,
            'lyrics' => $this->lyrics,
            'language' => $this->language,
            'genre' => $this->genre,
            'mood' => $this->mood,
            'vocal_type' => $this->vocalType,
            'tempo_bpm' => $this->tempoBpm,
            'instruments' => $this->instruments,
            'duration_seconds' => $this->durationSeconds,
            'description' => $this->description,
            'voice_style' => $this->voiceStyle,
        ];
    }
}
