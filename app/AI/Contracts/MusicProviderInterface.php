<?php

namespace App\AI\Contracts;

use App\AI\DTOs\LyricsRequest;
use App\AI\DTOs\LyricsResult;
use App\AI\DTOs\MusicRequest;
use App\AI\DTOs\MusicResult;
use App\AI\DTOs\VocalsRequest;
use App\AI\DTOs\VocalsResult;
use App\AI\DTOs\SongRequest;
use App\AI\DTOs\SongResult;
use App\AI\DTOs\ProviderStatus;

/**
 * Every AI provider (cloud API, free-tier API, or local/open-source model)
 * MUST implement this interface. The AiRouterService only ever talks to
 * providers through this contract, so a provider can be swapped, added,
 * or removed without touching the router or any application code.
 */
interface MusicProviderInterface
{
    /**
     * Unique slug for this provider, matches ai_providers.slug in DB.
     */
    public function getSlug(): string;

    /**
     * Human readable name for logs / admin UI.
     */
    public function getName(): string;

    /**
     * Generate original lyrics from a structured request.
     *
     * @throws \App\AI\Exceptions\RateLimitException
     * @throws \App\AI\Exceptions\QuotaExceededException
     * @throws \App\AI\Exceptions\TemporaryProviderException
     * @throws \App\AI\Exceptions\ProviderAuthException
     */
    public function generateLyrics(LyricsRequest $request): LyricsResult;

    /**
     * Generate instrumental / music track.
     */
    public function generateMusic(MusicRequest $request): MusicResult;

    /**
     * Generate vocals (if the provider supports vocal synthesis separately).
     */
    public function generateVocals(VocalsRequest $request): VocalsResult;

    /**
     * Generate a complete song (lyrics + music + vocals) in one call,
     * for providers that support end-to-end generation.
     */
    public function generateSong(SongRequest $request): SongResult;

    /**
     * Whether this provider is currently reachable/healthy.
     * Used by the scheduled health-check command.
     */
    public function getStatus(): ProviderStatus;

    /**
     * Remaining quota for today/this month, as reported by the provider
     * itself (if it exposes this) or as tracked locally in ai_usage_logs.
     *
     * @return array{daily_remaining:int|null, monthly_remaining:int|null}
     */
    public function getRemainingQuota(): array;

    /**
     * Does this provider support generating vocals directly,
     * or does the router need to pair it with a separate vocal provider?
     */
    public function supportsVocals(): bool;

    /**
     * Does this provider support full end-to-end song generation
     * (as opposed to only lyrics, or only instrumental)?
     */
    public function supportsFullSong(): bool;

    /**
     * Which router operations this provider can actually handle, e.g.
     * ['generate_lyrics'] for a text-only provider, or
     * ['generate_lyrics','generate_music','generate_vocals','generate_song']
     * for a full-stack provider. AiRouterService uses this to SKIP a
     * provider immediately for an operation it doesn't support, instead
     * of wasting an attempt (and retry/backoff time) on a call that is
     * guaranteed to fail.
     *
     * Valid operation strings: 'generate_lyrics', 'generate_music',
     * 'generate_vocals', 'generate_song'.
     */
    public function supportedOperations(): array;
}
