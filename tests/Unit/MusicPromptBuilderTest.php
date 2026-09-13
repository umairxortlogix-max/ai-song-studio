<?php

namespace Tests\Unit;

use App\AI\DTOs\SongRequest;
use App\AI\Services\MusicPromptBuilder;
use PHPUnit\Framework\TestCase;

class MusicPromptBuilderTest extends TestCase
{
    public function test_it_builds_a_structured_prompt_with_all_fields(): void
    {
        $builder = new MusicPromptBuilder();

        $request = new SongRequest(
            songId: null,
            title: 'Tere Baad',
            lyrics: null,
            language: 'urdu',
            genre: '90s Bollywood Romantic',
            mood: 'Sad Romantic',
            vocalType: 'male',
            tempoBpm: 82,
            instruments: ['Harmonium', 'Sarangi', 'Tabla'],
        );

        $prompt = $builder->build($request);

        $this->assertStringContainsString('Tere Baad', $prompt);
        $this->assertStringContainsString('82 BPM', $prompt);
        $this->assertStringContainsString('Harmonium, Sarangi, Tabla', $prompt);
    }

    public function test_lyrics_prompt_explicitly_requires_original_content(): void
    {
        $builder = new MusicPromptBuilder();

        $request = new SongRequest(
            songId: null, title: 'Test', lyrics: null, language: 'english',
            genre: 'pop', mood: 'happy', vocalType: 'female', tempoBpm: 120,
        );

        $prompt = $builder->buildLyricsPrompt($request);

        $this->assertStringContainsString('ORIGINAL', $prompt);
        $this->assertStringContainsString('do not reproduce', $prompt);
    }
}
