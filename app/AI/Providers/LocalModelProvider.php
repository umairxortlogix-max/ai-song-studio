<?php

namespace App\AI\Providers;

use App\AI\DTOs\LyricsRequest;
use App\AI\DTOs\LyricsResult;
use App\AI\DTOs\MusicRequest;
use App\AI\DTOs\MusicResult;
use App\AI\DTOs\ProviderStatus;
use App\AI\DTOs\SongRequest;
use App\AI\DTOs\SongResult;
use App\AI\DTOs\VocalsRequest;
use App\AI\DTOs\VocalsResult;
use App\AI\Exceptions\TemporaryProviderException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Provider D — LAST-RESORT FALLBACK.
 *
 * Talks over plain HTTP to a self-hosted inference server (Python
 * FastAPI wrapping an open-source model, Ollama, ComfyUI, etc. — see
 * section 14 of the spec). Because it's self-hosted, it has no
 * external quota, so it never throws QuotaExceededException — it's
 * the provider that keeps the app working even when every cloud
 * provider above it is exhausted or down.
 *
 * Set AI_LOCAL_MODEL_URL in .env to point at your inference server.
 */
class LocalModelProvider extends AbstractProvider
{
    public function generateLyrics(LyricsRequest $request): LyricsResult
    {
        $response = $this->callLocalServer('/generate/lyrics', [
            'theme' => $request->theme,
            'language' => $request->language,
            'genre' => $request->genre,
            'mood' => $request->mood,
        ]);

        return new LyricsResult(lyrics: $response['lyrics'] ?? '', providerSlug: $this->getSlug());
    }

    public function generateMusic(MusicRequest $request): MusicResult
    {
        $response = $this->callLocalServer('/generate/music', [
            'genre' => $request->genre,
            'mood' => $request->mood,
            'tempo_bpm' => $request->tempoBpm,
            'instruments' => $request->instruments,
            'duration_seconds' => $request->durationSeconds,
        ]);

        $path = $this->saveBase64Audio($response['audio_base64'] ?? '', 'instrumental');

        return new MusicResult(filePath: $path, providerSlug: $this->getSlug(), durationSeconds: $request->durationSeconds);
    }

    public function generateVocals(VocalsRequest $request): VocalsResult
    {
        $response = $this->callLocalServer('/generate/vocals', [
            'lyrics' => $request->lyrics,
            'vocal_type' => $request->vocalType,
            'language' => $request->language,
            'voice_style' => $request->voiceStyle,
        ]);

        $path = $this->saveBase64Audio($response['audio_base64'] ?? '', 'vocals');

        return new VocalsResult(filePath: $path, providerSlug: $this->getSlug());
    }

    public function generateSong(SongRequest $request): SongResult
    {
        $lyrics = $request->lyrics ?: $this->generateLyrics(new LyricsRequest(
            theme: $request->description ?: $request->title,
            language: $request->language,
            genre: $request->genre,
            mood: $request->mood,
        ))->lyrics;

        $music = $this->generateMusic(new MusicRequest(
            genre: $request->genre,
            mood: $request->mood,
            tempoBpm: $request->tempoBpm,
            instruments: $request->instruments,
            durationSeconds: $request->durationSeconds,
            lyrics: $lyrics,
        ));

        $vocals = $request->vocalType !== 'instrumental'
            ? $this->generateVocals(new VocalsRequest(
                lyrics: $lyrics,
                vocalType: $request->vocalType,
                language: $request->language,
                voiceStyle: $request->voiceStyle,
            ))
            : null;

        return new SongResult(
            lyrics: $lyrics,
            instrumentalPath: $music->filePath,
            vocalsPath: $vocals?->filePath,
            finalPath: null,
            providerSlug: $this->getSlug(),
        );
    }

    protected function requiresApiKey(): bool
    {
        return false;
    }

    public function supportsVocals(): bool
    {
        return true;
    }

    public function supportsFullSong(): bool
    {
        return true;
    }

    public function supportedOperations(): array
    {
        return ['generate_lyrics', 'generate_music', 'generate_vocals', 'generate_song'];
    }

    public function getStatus(): ProviderStatus
    {
        try {
            $response = $this->http(5)->get(rtrim($this->baseUrl(), '/') . '/health');
            return $response->successful() ? ProviderStatus::Healthy : ProviderStatus::Error;
        } catch (\Throwable) {
            return ProviderStatus::Error;
        }
    }

    private function callLocalServer(string $path, array $payload): array
    {
        $url = rtrim($this->baseUrl(), '/') . $path;

        $this->logOutgoingRequest('POST', $url, $payload, [], 'local_inference');

        try {
            $response = $this->http(120)->post($url, $payload);
        } catch (\Throwable $e) {
            throw new TemporaryProviderException($this->getSlug(), 'Local inference server unreachable: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new TemporaryProviderException($this->getSlug(), "Local server returned HTTP {$response->status()}");
        }

        return $response->json() ?? [];
    }

    private function saveBase64Audio(string $base64, string $label): string
    {
        if (blank($base64)) {
            throw new TemporaryProviderException($this->getSlug(), 'Local server returned no audio data');
        }

        $path = 'songs/tmp/' . Str::uuid() . "_{$label}.wav";
        Storage::disk('public')->put($path, base64_decode($base64));

        return $path;
    }
}
