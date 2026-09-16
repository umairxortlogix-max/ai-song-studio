<?php

namespace App\AI\Providers;

use App\AI\Contracts\MusicProviderInterface;
use App\AI\DTOs\ProviderStatus;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    /**
     * Resolve a provider setting.
     *
     * The ai_providers ROW wins; config('services.ai.*') (i.e. .env) is only a
     * fallback for rows that have no value. The previous order was reversed,
     * which meant every change saved in the admin panel was silently ignored
     * and the row shown in the UI did not match what was actually called.
     */
    protected function configuredProviderValue(string $field, mixed $fallback = null): mixed
    {
        if (is_string($fallback) && trim($fallback) !== '') {
            return $fallback;
        }

        if ($fallback !== null && ! is_string($fallback)) {
            return $fallback;
        }

        $providerType = (string) ($this->providerModel->provider_type ?? '');

        if ($providerType !== '') {
            $configValue = config('services.ai.' . $providerType . '.' . $field, null);

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
        if (! config('ai.log_provider_requests', true)) {
            return;
        }

        $safeHeaders = [];

        foreach ($headers as $key => $value) {
            $safeHeaders[$key] = in_array(strtolower((string) $key), ['authorization', 'x-api-key', 'api-key', 'token'], true)
                ? '[REDACTED]'
                : $value;
        }

        $safePayload = $payload;

        foreach (['api_key', 'token', 'authorization', 'secret'] as $sensitive) {
            unset($safePayload[$sensitive]);
        }

        Log::info('AI Provider request', [
            'provider' => $this->getSlug(),
            'operation' => $operation,
            'method' => strtoupper($method),
            'url' => $url,
            'headers' => $safeHeaders,
            'payload' => $safePayload,
        ]);
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

    /**
     * Default health probe: a provider is only "healthy" if it is actually
     * configured (base URL + credential present). Returning the stored DB
     * status here made ai:health-check write back the value it just read,
     * so a provider marked error/quota_exhausted could never recover.
     * Providers with a real /health or /account endpoint should override this.
     */
    public function getStatus(): ProviderStatus
    {
        if (blank($this->baseUrl())) {
            return ProviderStatus::Error;
        }

        if ($this->requiresApiKey() && blank($this->apiKey())) {
            return ProviderStatus::Error;
        }

        return ProviderStatus::Healthy;
    }

    protected function requiresApiKey(): bool
    {
        return true;
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
