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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EXAMPLE Provider B — async prediction API pattern (e.g. Replicate's
 * free-credits tier running an open-source music model such as
 * MusicGen). Demonstrates polling a job until it completes, still
 * within the same MusicProviderInterface contract.
 */
class ReplicateProvider extends AbstractProvider
{
    public function generateLyrics(LyricsRequest $request): LyricsResult
    {
        throw new TemporaryProviderException($this->getSlug(), 'This provider does not generate lyrics.');
    }

    public function generateMusic(MusicRequest $request): MusicResult
    {
        $prompt = trim(sprintf(
            '%s %s instrumental, tempo %s, instruments: %s',
            $request->mood,
            $request->genre,
            $request->tempoBpm ? "{$request->tempoBpm} BPM" : 'moderate',
            implode(', ', $request->instruments) ?: 'orchestral'
        ));

        $url = rtrim($this->baseUrl(), '/') . '/models/' . $this->modelName() . '/predictions';
        $payload = [
            'input' => [
                'prompt' => $prompt,
                'duration' => $request->durationSeconds ?? 30,
            ],
        ];

        $this->logOutgoingRequest('POST', $url, $payload, ['Authorization' => 'Token [REDACTED]'], 'generate_music');

        $create = $this->http(30)
            ->withHeaders(['Authorization' => 'Token ' . $this->apiKey()])
            ->post($url, $payload);

        $this->throwForHttpErrors($create);

        $predictionId = $create->json('id');
        $pollUrl = $create->json('urls.get') ?? rtrim($this->baseUrl(), '/') . "/predictions/{$predictionId}";

        $audioUrl = $this->pollUntilComplete($pollUrl);

        $relativePath = $this->downloadToStorage($audioUrl, 'instrumental');

        return new MusicResult(filePath: $relativePath, providerSlug: $this->getSlug(), durationSeconds: $request->durationSeconds);
    }

    public function generateVocals(VocalsRequest $request): VocalsResult
    {
        $model = trim((string) $this->modelName());
        if (!str_contains(strtolower($model), 'bark') && !str_contains(strtolower($model), 'riffusion')) {
            throw new TemporaryProviderException($this->getSlug(), 'This Replicate model is not configured for vocal synthesis.');
        }

        $payload = [
            'version' => $model,
            'input' => [
                'text' => $request->lyrics,
                'prompt' => $request->lyrics,
                'voice' => $request->voiceStyle ?? 'neutral',
            ],
        ];

        $url = rtrim($this->baseUrl(), '/') . '/predictions';
        $this->logOutgoingRequest('POST', $url, $payload, ['Authorization' => 'Token [REDACTED]'], 'generate_vocals');

        $create = $this->http(30)
            ->withHeaders(['Authorization' => 'Token ' . $this->apiKey()])
            ->post($url, $payload);

        $this->throwForHttpErrors($create);

        $predictionId = $create->json('id');
        $pollUrl = $create->json('urls.get') ?? rtrim($this->baseUrl(), '/') . "/predictions/{$predictionId}";
        $audioUrl = $this->pollUntilComplete($pollUrl);

        $relativePath = $this->downloadToStorage($audioUrl, 'vocals');

        return new VocalsResult(filePath: $relativePath, providerSlug: $this->getSlug());
    }

    public function generateSong(SongRequest $request): SongResult
    {
        $music = $this->generateMusic(new MusicRequest(
            genre: $request->genre,
            mood: $request->mood,
            tempoBpm: $request->tempoBpm,
            instruments: $request->instruments,
            durationSeconds: $request->durationSeconds,
            lyrics: $request->lyrics,
            description: $request->description,
        ));

        return new SongResult(
            lyrics: $request->lyrics,
            instrumentalPath: $music->filePath,
            vocalsPath: null,
            finalPath: null,
            providerSlug: $this->getSlug(),
        );
    }

    public function supportsFullSong(): bool
    {
        return false;
    }

    public function supportedOperations(): array
    {
        $model = strtolower((string) ($this->modelName() ?? ''));
        $operations = [];

        if (str_contains($model, 'musicgen')) {
            $operations[] = 'generate_music';
        }

        if (str_contains($model, 'bark') || str_contains($model, 'riffusion')) {
            $operations[] = 'generate_vocals';
        }

        return $operations;
    }

    private function pollUntilComplete(string $pollUrl, int $maxAttempts = 30, int $delaySeconds = 2): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->logOutgoingRequest('GET', $pollUrl, [], ['Authorization' => 'Token [REDACTED]'], 'poll_prediction');
            $poll = $this->http(20)->withHeaders(['Authorization' => 'Token ' . $this->apiKey()])->get($pollUrl);
            $this->throwForHttpErrors($poll);

            $status = $poll->json('status');

            if ($status === 'succeeded') {
                $output = $poll->json('output');
                return is_array($output) ? ($output[0] ?? '') : (string) $output;
            }

            if ($status === 'failed' || $status === 'canceled') {
                throw new TemporaryProviderException($this->getSlug(), "Prediction {$status}");
            }

            sleep($delaySeconds);
        }

        throw new TemporaryProviderException($this->getSlug(), 'Prediction timed out waiting for completion');
    }

    private function downloadToStorage(string $url, string $label): string
    {
        $response = $this->http(60)->get($url);

        if (!$response->successful()) {
            throw new TemporaryProviderException($this->getSlug(), 'Failed to download generated audio');
        }

        $path = 'songs/tmp/' . Str::uuid() . "_{$label}.mp3";
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function throwForHttpErrors($response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new ProviderAuthException($this->getSlug());
        }

        if ($status === 429) {
            throw new RateLimitException($this->getSlug());
        }

        if ($status === 402 || Str::contains(strtolower($response->body()), 'quota')) {
            throw new QuotaExceededException($this->getSlug());
        }

        if ($status >= 500) {
            throw new TemporaryProviderException($this->getSlug(), "HTTP {$status}");
        }

        throw new TemporaryProviderException($this->getSlug(), "HTTP {$status}");
    }
}
