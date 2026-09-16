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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $model = trim((string) $this->modelName());
        if (! str_contains(strtolower($model), 'musicgen')) {
            throw new TemporaryProviderException($this->getSlug(), 'This Hugging Face model is not configured for music generation.');
        }

        $prompt = trim(sprintf(
            '%s %s instrumental, tempo %s, style: %s, instruments: %s',
            $request->mood,
            $request->genre,
            $request->tempoBpm ? "{$request->tempoBpm} BPM" : 'moderate',
            $request->genre,
            implode(', ', $request->instruments) ?: 'acoustic instruments'
        ));

        $url = rtrim($this->baseUrl(), '/') . '/models/' . $model;
        $payload = [
            'inputs' => $prompt,
            'parameters' => [
                'do_sample' => true,
                'temperature' => 0.8,
            ],
        ];

        $this->logOutgoingRequest('POST', $url, $payload, ['Authorization' => 'Bearer [REDACTED]'], 'generate_music');

        $response = $this->http(60)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey()])
            ->post($url, $payload);

        $this->throwForHttpErrors($response);

        $audio = $response->body();
        if (blank($audio)) {
            throw new TemporaryProviderException($this->getSlug(), 'Empty audio payload returned by Hugging Face music model.');
        }

        $path = 'songs/tmp/' . Str::uuid() . '_instrumental.wav';
        Storage::disk('public')->put($path, $audio);

        return new MusicResult(filePath: $path, providerSlug: $this->getSlug(), durationSeconds: $request->durationSeconds ?? 30);
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
        $model = strtolower((string) ($this->modelName() ?? ''));

        if (str_contains($model, 'musicgen')) {
            return ['generate_music'];
        }

        return [];
    }

    private function throwForHttpErrors($response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $responseBody = strtolower((string) $response->body());

        if ($status === 401 || $status === 403) {
            if (str_contains($responseBody, 'inference providers') || str_contains($responseBody, 'permission') || str_contains($responseBody, 'scope')) {
                Log::warning('Hugging Face permission scope warning', [
                    'provider' => $this->getSlug(),
                    'model' => $this->providerModel->model,
                    'issue' => 'Token may be missing Inference Providers permission or model access.',
                    'fix' => 'Enable the token access for the target Inference Provider and verify your model is permitted for this account.',
                ]);
            }

            throw new ProviderAuthException($this->getSlug(), 'Invalid or missing API key or missing Inference Providers permission');
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
