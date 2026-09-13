<?php

namespace App\AI\Providers;

use App\AI\Contracts\MusicProviderInterface;
use App\AI\DTOs\ProviderStatus;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing for all providers: HTTP client setup, quota lookups
 * from the DB row, and default supportsVocals()/supportsFullSong()
 * that concrete providers can override.
 */
abstract class AbstractProvider implements MusicProviderInterface
{
    public function __construct(protected readonly AiProvider $providerModel) {}

    public function getSlug(): string
    {
        return $this->providerModel->slug;
    }

    public function getName(): string
    {
        return $this->providerModel->name;
    }

    protected function apiKey(): ?string
    {
        // Decrypted automatically via the AiProvider model's cast, see App\Models\AiProvider.
        return $this->providerModel->api_key;
    }

    protected function baseUrl(): ?string
    {
        return $this->providerModel->api_base_url;
    }

    protected function http(int $timeoutSeconds = 30)
    {
        return Http::timeout($timeoutSeconds)->retry(0);
    }

    public function getRemainingQuota(): array
    {
        $daily = $this->providerModel->daily_limit
            ? max(0, $this->providerModel->daily_limit - $this->providerModel->used_today)
            : null;

        $monthly = $this->providerModel->monthly_limit
            ? max(0, $this->providerModel->monthly_limit - $this->providerModel->used_this_month)
            : null;

        return ['daily_remaining' => $daily, 'monthly_remaining' => $monthly];
    }

    public function getStatus(): ProviderStatus
    {
        return ProviderStatus::tryFrom($this->providerModel->status) ?? ProviderStatus::Error;
    }

    public function supportsVocals(): bool
    {
        return false;
    }

    public function supportsFullSong(): bool
    {
        return false;
    }

    /**
     * Default: no operations supported. Every concrete provider MUST
     * override this to declare what it can actually do — this is what
     * stops the router from wasting an attempt on an operation that is
     * guaranteed to fail (e.g. asking a lyrics-only provider to make music).
     */
    public function supportedOperations(): array
    {
        return [];
    }
}
