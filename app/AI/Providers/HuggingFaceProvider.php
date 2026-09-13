<?php

namespace App\AI\Providers;

use App\AI\DTOs\LyricsRequest;
use App\AI\DTOs\LyricsResult;
use App\AI\DTOs\MusicRequest;
use App\AI\DTOs\MusicResult;
use App\AI\DTOs\SongRequest;
use App\AI\DTOs\SongResult;
use App\AI\DTOs\VocalsRequest;
use App\AI\DTOs\VocalsResult;
use App\AI\Exceptions\ProviderAuthException;
use App\AI\Exceptions\QuotaExceededException;
use App\AI\Exceptions\RateLimitException;
use App\AI\Exceptions\TemporaryProviderException;
use App\AI\Services\MusicPromptBuilder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * EXAMPLE Provider A — free-tier text/audio inference API pattern
 * (e.g. Hugging Face Inference API's free tier). Handles lyrics
 * generation and, if the configured model supports it, text-to-music.
 *
 * Swap the base URL / model / auth header for whichever free provider
 * you actually register in the ai_providers table — the router does
 * not care which real-world service this class wraps.
 */
class HuggingFaceProvider extends AbstractProvider
{
    public function generateLyrics(LyricsRequest $request): LyricsResult
    {
        $prompt = "Write ORIGINAL {$request->mood} {$request->genre} song lyrics in {$request->language} about: {$request->theme}. Do not copy any existing song.";

        $response = $this->http(45)
            ->withToken($this->apiKey())
            ->post(rtrim($this->baseUrl(), '/') . '/models/' . $this->providerModel->model, [
                'inputs' => $prompt,
                'parameters' => ['max_new_tokens' => 600, 'temperature' => 0.9],
            ]);

        $this->throwForHttpErrors($response);

        $text = $response->json('0.generated_text') ?? $response->json('generated_text') ?? '';

        if (blank($text)) {
            throw new TemporaryProviderException($this->getSlug(), 'Empty response from provider');
        }

        return new LyricsResult(lyrics: trim($text), providerSlug: $this->getSlug());
    }

    public function generateMusic(MusicRequest $request): MusicResult
    {
        throw new TemporaryProviderException($this->getSlug(), 'This provider does not support instrumental generation.');
    }

    public function generateVocals(VocalsRequest $request): VocalsResult
    {
        throw new TemporaryProviderException($this->getSlug(), 'This provider does not support vocal synthesis.');
    }

    public function generateSong(SongRequest $request): SongResult
    {
        $lyrics = $this->generateLyrics(new LyricsRequest(
            theme: $request->description ?: $request->title,
            language: $request->language,
            genre: $request->genre,
            mood: $request->mood,
            title: $request->title,
        ));

        return new SongResult(
            lyrics: $lyrics->lyrics,
            instrumentalPath: null,
            vocalsPath: null,
            finalPath: null,
            providerSlug: $this->getSlug(),
        );
    }

    public function supportsFullSong(): bool
    {
        return false; // lyrics only in this example — router will chain to a music provider next
    }

    public function supportedOperations(): array
    {
        return ['generate_lyrics']; // this provider cannot make music or vocals
    }

    private function throwForHttpErrors($response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new ProviderAuthException($this->getSlug(), 'Invalid or missing API key');
        }

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');
            throw new RateLimitException($this->getSlug(), 'Rate limited by provider', $retryAfter ? (int) $retryAfter : null);
        }

        if ($status === 402 || Str::contains(strtolower($response->body()), ['quota', 'insufficient credits'])) {
            throw new QuotaExceededException($this->getSlug());
        }

        if ($status >= 500 || $status === 408) {
            throw new TemporaryProviderException($this->getSlug(), "Provider returned HTTP {$status}");
        }

        throw new TemporaryProviderException($this->getSlug(), "Unexpected provider response (HTTP {$status})");
    }
}
