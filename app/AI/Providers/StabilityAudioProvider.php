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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EXAMPLE Provider C — direct synchronous audio-generation API
 * pattern (e.g. Stability AI's audio API free tier). Third fallback
 * in priority order.
 */
class StabilityAudioProvider extends AbstractProvider
{
    public function generateLyrics(LyricsRequest $request): LyricsResult
    {
        throw new TemporaryProviderException($this->getSlug(), 'This provider does not generate lyrics.');
    }

    public function generateMusic(MusicRequest $request): MusicResult
    {
        $prompt = trim(sprintf('%s %s, %s BPM, featuring %s', $request->mood, $request->genre, $request->tempoBpm ?? 90, implode(', ', $request->instruments) ?: 'acoustic instruments'));

        // Correct endpoint for Stability's Stable Audio 2.0 text-to-audio API.
        // Requires "Accept: audio/*" to get raw audio bytes back instead of JSON.
        $url = rtrim($this->baseUrl(), '/') . '/audio/stable-audio-2/text-to-audio';
        $payload = [
            ['name' => 'prompt', 'contents' => $prompt],
            ['name' => 'duration', 'contents' => (string) ($request->durationSeconds ?? 30)],
            ['name' => 'output_format', 'contents' => 'mp3'],
        ];

        $this->logOutgoingRequest('POST', $url, [
            'prompt' => $prompt,
            'duration' => (string) ($request->durationSeconds ?? 30),
            'output_format' => 'mp3',
        ], ['Accept' => 'audio/*', 'Authorization' => 'Bearer [REDACTED]'], 'generate_music');

        $response = $this->http(45)
            ->withToken($this->apiKey())
            ->withHeaders(['Accept' => 'audio/*'])
            ->asMultipart()
            ->post($url, $payload);

        $this->throwForHttpErrors($response);

        $path = 'songs/tmp/' . Str::uuid() . '_instrumental.mp3';
        Storage::disk('public')->put($path, $response->body());

        return new MusicResult(filePath: $path, providerSlug: $this->getSlug(), durationSeconds: $request->durationSeconds);
    }

    public function generateVocals(VocalsRequest $request): VocalsResult
    {
        throw new TemporaryProviderException($this->getSlug(), 'This provider does not synthesize vocals.');
    }

    public function generateSong(SongRequest $request): SongResult
    {
        $music = $this->generateMusic(new MusicRequest(
            genre: $request->genre,
            mood: $request->mood,
            tempoBpm: $request->tempoBpm,
            instruments: $request->instruments,
            durationSeconds: $request->durationSeconds,
        ));

        return new SongResult(
            lyrics: $request->lyrics,
            instrumentalPath: $music->filePath,
            vocalsPath: null,
            finalPath: null,
            providerSlug: $this->getSlug(),
        );
    }

    public function supportedOperations(): array
    {
        $model = strtolower((string) ($this->modelName() ?? ''));

        return str_contains($model, 'stable-audio') ? ['generate_music'] : [];
    }

    private function throwForHttpErrors($response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if (in_array($status, [401, 403])) {
            throw new ProviderAuthException($this->getSlug());
        }
        if ($status === 429) {
            throw new RateLimitException($this->getSlug());
        }
        if ($status === 402) {
            throw new QuotaExceededException($this->getSlug());
        }
        throw new TemporaryProviderException($this->getSlug(), "HTTP {$status}");
    }
}
