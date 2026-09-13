<?php

namespace App\AI\Services;

use App\AI\DTOs\SongRequest;

/**
 * Builds a single, provider-independent structured prompt from the
 * user's form input. Every provider receives the SAME structured
 * data — how each provider turns it into its own API payload is
 * entirely up to that provider's class.
 */
class MusicPromptBuilder
{
    public function build(SongRequest $request): string
    {
        $instruments = empty($request->instruments) ? 'none specified' : implode(', ', $request->instruments);
        $tempo = $request->tempoBpm ? "{$request->tempoBpm} BPM" : 'unspecified tempo';

        $lines = [
            "Title: {$request->title}",
            "Language: {$request->language}",
            "Genre: {$request->genre}",
            "Mood: {$request->mood}",
            "Vocal type: {$request->vocalType}",
            "Tempo: {$tempo}",
            "Instruments: {$instruments}",
        ];

        if ($request->voiceStyle) {
            $lines[] = "Voice style: {$request->voiceStyle}";
        }

        if ($request->durationSeconds) {
            $lines[] = "Target duration: {$request->durationSeconds} seconds";
        }

        if ($request->description) {
            $lines[] = "Additional description: {$request->description}";
        }

        if ($request->lyrics) {
            $lines[] = "Lyrics:\n{$request->lyrics}";
        }

        return implode("\n", $lines);
    }

    /**
     * Builds a lyrics-only prompt instructing the model to write
     * ORIGINAL lyrics (never reproduce existing copyrighted songs).
     */
    public function buildLyricsPrompt(SongRequest $request): string
    {
        $instruments = empty($request->instruments) ? '' : ' Instrumentation context: ' . implode(', ', $request->instruments) . '.';

        return sprintf(
            "Write completely ORIGINAL song lyrics (do not reproduce or closely imitate any existing copyrighted song). ".
            "Title theme: \"%s\". Language: %s. Genre: %s. Mood: %s. Vocal type: %s.%s ".
            "Structure: verse, chorus, verse, chorus, bridge, chorus.",
            $request->description ?: $request->title,
            $request->language,
            $request->genre,
            $request->mood,
            $request->vocalType,
            $instruments
        );
    }
}
