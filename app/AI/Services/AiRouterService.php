<?php

namespace App\AI\Services;

use App\AI\Contracts\MusicProviderInterface;
use App\AI\DTOs\ProviderStatus;
use App\AI\DTOs\SongRequest;
use App\AI\DTOs\SongResult;
use App\AI\Exceptions\AllProvidersUnavailableException;
use App\AI\Exceptions\ProviderException;
use App\AI\Exceptions\QuotaExceededException;
use App\AI\Exceptions\RateLimitException;
use App\AI\Exceptions\TemporaryProviderException;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\ProviderFailure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * THE CORE OF THE MULTI-PROVIDER FALLBACK SYSTEM.
 *
 * This service never hard-codes a provider. It reads active providers
 * from the `ai_providers` table (ordered by priority), resolves each
 * one to its concrete class from config('ai.providers'), and walks
 * down the list until one succeeds.
 *
 * Flow:
 *   Provider A (priority 1) -> quota/rate-limit/error -> Provider B (priority 2)
 *   -> ... -> Provider D (priority 4, e.g. local model) -> AllProvidersUnavailableException
 *
 * One provider ending a generation attempt (for any reason covered by
 * ProviderException) always triggers the next provider to start
 * automatically, in the same request/job — the user never has to
 * retry manually.
 */
class AiRouterService
{
    /** Max automatic retries for a single provider on a *temporary* failure. */
    private const MAX_RETRIES_PER_PROVIDER = 2;

    private const BACKOFF_BASE_MS = 300;

    public function __construct(
        private readonly ProviderResolver $resolver,
    ) {
        $this->validateConfiguredProviders();
    }

    /**
     * Attempt full end-to-end song generation, falling back across
     * every eligible provider in priority order.
     *
     * @throws AllProvidersUnavailableException
     */
    public function generateSong(SongRequest $request, ?int $userId = null): SongResult
    {
        return $this->attempt(operation: 'generate_song', request: $request, userId: $userId);
    }

    /**
     * Generic runner used by every generation stage (lyrics/music/vocals/
     * full song). Dispatches to the right provider method via invoke(),
     * based on $operation, and walks the provider priority list on failure.
     *
     * @throws AllProvidersUnavailableException
     */
    public function attempt(string $operation, mixed $request, ?int $userId = null): mixed
    {
        $requestId = (string) Str::uuid();
        $requestSummary = $this->summarizePayload($request);
        $startedAt = microtime(true);

        $providers = $this->getEligibleProvidersInOrder();
        $capabilityCheck = $this->buildCapabilityCheckResults($operation, $providers);

        if ($providers->isEmpty()) {
            Log::error('AI Router no eligible providers', [
                'request_id' => $requestId,
                'operation' => $operation,
                'eligible_provider_count' => 0,
                'provider_checks' => $capabilityCheck,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            throw new AllProvidersUnavailableException();
        }

        $attempted = [];
        $providerResults = [];

        foreach ($providers as $providerIndex => $providerModel) {
            $providerName = $providerModel->slug;
            $priority = (int) $providerModel->priority;
            $position = $providerIndex + 1;
            $attempted[] = $providerName;

            // Skip providers whose local usage counters show quota exhausted.
            if ($this->isQuotaExhaustedLocally($providerModel)) {
                $reason = 'local quota tracking shows exhausted';
                $providerResults[] = ['provider' => $providerName, 'outcome' => 'skipped', 'reason' => $reason, 'priority' => $priority];
                continue;
            }

            $provider = $this->resolver->resolve($providerModel);

            if (! $provider) {
                $reason = 'no concrete provider class registered';
                $providerResults[] = ['provider' => $providerName, 'outcome' => 'skipped', 'reason' => $reason, 'priority' => $priority];

                Log::warning('AI Router provider skipped', [
                    'request_id' => $requestId,
                    'provider' => $providerName,
                    'priority' => $priority,
                    'reason' => $reason,
                    'operation' => $operation,
                ]);
                continue;
            }

            $supported = $provider->supportedOperations();
            $model = trim((string) ($providerModel->model ?? ''));

            // If a model is blank, malformed, or does not advertise support for the
            // requested operation, we intentionally skip it and continue to the next
            // provider in priority order. This is the automatic fallback chain.
            if ($model === '' || ! in_array($operation, $supported, true)) {
                $reason = $model === ''
                    ? 'model is empty; moving to next provider'
                    : "provider supports [" . implode(', ', $supported ?: ['none']) . "] but not [{$operation}]";

                $providerResults[] = ['provider' => $providerName, 'outcome' => 'skipped', 'reason' => $reason, 'priority' => $priority];

                continue;
            }

            $result = $this->tryProviderWithRetries($provider, $providerModel, $operation, $request, $userId, $requestId);

            if ($result !== null) {
                $durationMs = $this->elapsedMs($startedAt);

                return $result;
            }

            // tryProviderWithRetries returning null means: move to next provider.
        }

        Log::error('AI Router all providers exhausted', [
            'request_id' => $requestId,
            'operation' => $operation,
            'attempted' => $attempted,
            'provider_results' => $providerResults,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        Log::error('AI Router no eligible providers details', [
            'request_id' => $requestId,
            'operation' => $operation,
            'provider_checks' => $this->buildCapabilityCheckResults($operation, $providers),
            'attempted' => $attempted,
            'provider_results' => $providerResults,
        ]);

        throw new AllProvidersUnavailableException($attempted);
    }

    /**
     * Try a single provider, retrying transient failures with
     * exponential backoff, but immediately giving up (returning null,
     * so the caller moves to the next provider) on quota/rate-limit/auth
     * failures — those are not worth retrying against the same provider.
     */
    private function tryProviderWithRetries(
        MusicProviderInterface $provider,
        AiProvider $providerModel,
        string $operation,
        mixed $request,
        ?int $userId,
        string $requestId,
    ): mixed {
        $attempt = 0;
        $failureReasons = [];

        while ($attempt <= self::MAX_RETRIES_PER_PROVIDER) {
            $attempt++;
            $startedAt = microtime(true);

            try {
                $response = $this->invoke($provider, $operation, $request);

                $responseTimeMs = $this->elapsedMs($startedAt);
                $this->recordSuccess($providerModel, $userId, $operation, $startedAt);

                return $response;
            } catch (RateLimitException $e) {
                $reason = 'rate_limit';
                $failureReasons[] = $reason . ': ' . $e->getMessage();
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::RateLimited);

                Log::warning('AI Router provider failure', [
                    'request_id' => $requestId,
                    'provider' => $providerModel->slug,
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'error_type' => 'RateLimitException',
                    'message' => $e->getMessage(),
                    'retry_after_seconds' => $e->retryAfterSeconds,
                    'decision' => 'move_to_next_provider',
                    'response_time_ms' => $this->elapsedMs($startedAt),
                ]);

                if ($e->retryAfterSeconds !== null && $e->retryAfterSeconds <= 5 && $attempt <= self::MAX_RETRIES_PER_PROVIDER) {
                    usleep($e->retryAfterSeconds * 1_000_000);
                    continue;
                }

                return null; // move on
            } catch (QuotaExceededException $e) {
                $reason = 'quota_exceeded';
                $failureReasons[] = $reason . ': ' . $e->getMessage();
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::QuotaExhausted);

                Log::warning('AI Router provider failure', [
                    'request_id' => $requestId,
                    'provider' => $providerModel->slug,
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'error_type' => 'QuotaExceededException',
                    'message' => $e->getMessage(),
                    'decision' => 'move_to_next_provider',
                    'response_time_ms' => $this->elapsedMs($startedAt),
                ]);

                return null; // never retry this provider again today
            } catch (TemporaryProviderException $e) {
                $reason = 'temporary_provider_failure';
                $failureReasons[] = $reason . ': ' . $e->getMessage();
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);

                Log::warning('AI Router temporary provider failure', [
                    'request_id' => $requestId,
                    'provider' => $providerModel->slug,
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'error_type' => 'TemporaryProviderException',
                    'message' => $e->getMessage(),
                    'retry_count' => $attempt,
                    'decision' => $attempt <= self::MAX_RETRIES_PER_PROVIDER ? 'retry_same_provider' : 'move_to_next_provider',
                    'response_time_ms' => $this->elapsedMs($startedAt),
                ]);

                if ($attempt <= self::MAX_RETRIES_PER_PROVIDER) {
                    $this->backoff($attempt);
                    continue;
                }

                $this->markStatus($providerModel, ProviderStatus::Error);
                return null;
            } catch (ProviderException $e) {
                $reason = 'provider_auth_or_validation_error';
                $failureReasons[] = $reason . ': ' . $e->getMessage();
                // Auth errors and any other provider-specific failure.
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::Error);

                Log::warning('AI Router provider failure', [
                    'request_id' => $requestId,
                    'provider' => $providerModel->slug,
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'error_type' => get_class($e),
                    'message' => $e->getMessage(),
                    'decision' => 'move_to_next_provider',
                    'response_time_ms' => $this->elapsedMs($startedAt),
                ]);

                return null;
            } catch (Throwable $e) {
                $failureReasons[] = 'unexpected_error: ' . $e->getMessage();
                // Never let an unexpected exception bubble up with internal details.
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::Error);

                Log::error('AI Router unexpected provider error', [
                    'request_id' => $requestId,
                    'provider' => $providerModel->slug,
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'error_type' => get_class($e),
                    'message' => $e->getMessage(),
                    'decision' => 'move_to_next_provider',
                    'response_time_ms' => $this->elapsedMs($startedAt),
                ]);

                return null;
            }
        }

        Log::warning('AI Router provider exhausted', [
            'request_id' => $requestId,
            'provider' => $providerModel->slug,
            'operation' => $operation,
            'retries_used' => $attempt,
            'failure_reasons' => $failureReasons,
        ]);

        return null;
    }

    private function invoke(MusicProviderInterface $provider, string $operation, mixed $request): mixed
    {
        return match ($operation) {
            'generate_song' => $provider->generateSong($request),
            'generate_lyrics' => $provider->generateLyrics($request),
            'generate_music' => $provider->generateMusic($request),
            'generate_vocals' => $provider->generateVocals($request),
            default => throw new \InvalidArgumentException("Unknown operation [$operation]"),
        };
    }

    /**
     * Active providers ordered by priority (1 = tried first),
     * filtered to only those the admin has enabled and that
     * currently report a usable status.
     */
    private function getEligibleProvidersInOrder()
    {
        return AiProvider::query()
            ->where('is_active', true)
            ->whereNotIn('status', [ProviderStatus::Disabled->value])
            ->orderBy('priority')
            ->get();
    }

    private function isQuotaExhaustedLocally(AiProvider $providerModel): bool
    {
        if ($providerModel->daily_limit && $providerModel->used_today >= $providerModel->daily_limit) {
            return true;
        }

        if ($providerModel->monthly_limit && $providerModel->used_this_month >= $providerModel->monthly_limit) {
            return true;
        }

        return $providerModel->status === ProviderStatus::QuotaExhausted->value;
    }

    private function markStatus(AiProvider $providerModel, ProviderStatus $status): void
    {
        $providerModel->status = $status->value;
        $providerModel->last_used_at = now();

        if ($status !== ProviderStatus::Healthy) {
            $providerModel->increment('failure_count');
        } else {
            $providerModel->failure_count = 0;
        }

        $providerModel->save();
    }

    private function recordSuccess(AiProvider $providerModel, ?int $userId, string $operation, float $startedAt): void
    {
        $providerModel->increment('used_today');
        $providerModel->increment('used_this_month');
        $providerModel->status = ProviderStatus::Healthy->value;
        $providerModel->failure_count = 0;
        $providerModel->last_used_at = now();
        $providerModel->save();

        AiUsageLog::create([
            'user_id' => $userId,
            'provider_id' => $providerModel->id,
            'operation' => $operation,
            'model' => $providerModel->model,
            'status' => 'success',
            'response_time' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function recordFailure(AiProvider $providerModel, ?int $userId, string $operation, Throwable $e, float $startedAt): void
    {
        AiUsageLog::create([
            'user_id' => $userId,
            'provider_id' => $providerModel->id,
            'operation' => $operation,
            'model' => $providerModel->model,
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'response_time' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        ProviderFailure::create([
            'provider_id' => $providerModel->id,
            'operation' => $operation,
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    private function backoff(int $attempt): void
    {
        $delayMs = self::BACKOFF_BASE_MS * (2 ** ($attempt - 1));
        usleep($delayMs * 1000);
    }

    private function validateConfiguredProviders(): void
    {
        foreach (AiProvider::query()->where('is_active', true)->get() as $provider) {
            $model = trim((string) ($provider->model ?? ''));

            if ($model === '') {
                Log::warning('AI Router provider config warning', [
                    'provider' => $provider->slug,
                    'provider_type' => $provider->provider_type,
                    'issue' => 'model is empty',
                    'fix' => 'Set the corresponding AI_PROVIDER_*_MODEL env var and re-seed or re-save the provider.',
                ]);

                continue;
            }

            $supported = $this->detectSupportedOperationsFromModel($provider->provider_type, $model);
            if ($supported === []) {
                Log::warning('AI Router provider config warning', [
                    'provider' => $provider->slug,
                    'provider_type' => $provider->provider_type,
                    'model' => $model,
                    'issue' => 'model does not match a known supported capability',
                    'known_models' => $this->knownModelHints($provider->provider_type),
                    'supported_operations' => $supported,
                ]);
            }
        }
    }

    private function buildStartupCapabilityMatrix(): array
    {
        $rows = [];

        foreach (AiProvider::query()->where('is_active', true)->orderBy('priority')->get() as $provider) {
            $rows[] = [
                'provider' => $provider->slug,
                'provider_type' => $provider->provider_type,
                'model' => $provider->model,
                'supported_operations' => $this->detectSupportedOperationsFromModel($provider->provider_type, $provider->model),
            ];
        }

        return $rows;
    }

    private function buildCapabilityCheckResults(string $operation, $providers): array
    {
        $result = [];

        foreach ($providers as $provider) {
            $providerModel = $provider->model ?? '';
            $supported = $this->detectSupportedOperationsFromModel($provider->provider_type, $providerModel);
            $result[] = [
                'provider' => $provider->slug,
                'provider_type' => $provider->provider_type,
                'model' => $providerModel,
                'supported_operations' => $supported,
                'requested_operation' => $operation,
                'included' => in_array($operation, $supported, true),
                'reason' => in_array($operation, $supported, true) ? 'eligible' : 'missing requested operation in supportedOperations list',
            ];
        }

        return $result;
    }

    private function detectSupportedOperationsFromModel(?string $providerType, ?string $model): array
    {
        $normalized = strtolower((string) ($model ?? ''));

        return match ($providerType) {
            'huggingface' => (str_contains($normalized, 'musicgen') ? ['generate_music'] : []),
            'replicate' => array_values(array_filter([
                str_contains($normalized, 'musicgen') ? 'generate_music' : null,
                str_contains($normalized, 'bark') || str_contains($normalized, 'riffusion') ? 'generate_vocals' : null,
            ])),
            'stability' => (str_contains($normalized, 'stable-audio') ? ['generate_music'] : []),
            'local' => ['generate_lyrics', 'generate_music', 'generate_vocals'],
            default => [],
        };
    }

    private function knownModelHints(?string $providerType): array
    {
        return match ($providerType) {
            'huggingface' => ['facebook/musicgen-small'],
            'replicate' => ['meta/musicgen', 'suno-ai/bark', 'riffusion/riffusion'],
            'stability' => ['stable-audio'],
            'local' => ['local-open-source'],
            default => [],
        };
    }

    private function summarizePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $this->sanitizePayload($payload);
        }

        if (is_object($payload)) {
            return ['class' => get_class($payload), 'properties' => $this->sanitizePayload((array) $payload)];
        }

        return ['value' => $payload];
    }

    private function sanitizePayload(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $normalizedKey = strtolower((string) $key);
                $result[$key] = in_array($normalizedKey, ['api_key', 'token', 'authorization', 'secret', 'password'], true)
                    ? '[REDACTED]'
                    : $this->sanitizePayload($item);
            }

            return $result;
        }

        if (is_string($value)) {
            return strlen($value) > 200 ? substr($value, 0, 197) . '...' : $value;
        }

        return $value;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
