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

    protected function configuredProviderValue(string $field, mixed $fallback = null): mixed
    {
        $providerType = (string) ($this->providerModel->provider_type ?? '');

        if ($providerType !== '') {
            $configValue = config('services.ai.' . $providerType . '.' . $field, null);

            if (is_string($configValue) && trim($configValue) !== '') {
                return $configValue;
            }

            if ($configValue !== null && $configValue !== '') {
                return $configValue;
            }
        }

        return $fallback;
    }

 protected function apiKey(): ?string
{
    $value = $this->configuredProviderValue('key', $this->providerModel->api_key);

    return (is_string($value) && trim($value) !== '') ? $value : null;
}

    protected function baseUrl(): ?string
    {
        return $this->configuredProviderValue('url', $this->providerModel->api_base_url);
    }

    protected function modelName(): ?string
    {
        return $this->configuredProviderValue('model', $this->providerModel->model);
    }

    protected function http(int $timeoutSeconds = 30)
    {
        return Http::timeout($timeoutSeconds)->retry(0);
    }

    protected function logOutgoingRequest(string $method, string $url, array $payload = [], array $headers = [], string $operation = 'unknown'): void
    {
        // Intentionally silent. Provider request details are not logged at info level to avoid noisy production logs.
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
