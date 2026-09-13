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
    ) {}

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
        $providers = $this->getEligibleProvidersInOrder();

        if ($providers->isEmpty()) {
            throw new AllProvidersUnavailableException();
        }

        $attempted = [];

        foreach ($providers as $providerModel) {
            $attempted[] = $providerModel->slug;

            // Skip providers whose local usage counters show quota exhausted.
            if ($this->isQuotaExhaustedLocally($providerModel)) {
                Log::info("AI Router: skipping {$providerModel->slug} — local quota tracking shows exhausted.");
                continue;
            }

            $provider = $this->resolver->resolve($providerModel);

            if (! $provider) {
                Log::warning("AI Router: no concrete class registered for provider {$providerModel->slug}, skipping.");
                continue;
            }

            // Skip providers that don't support this operation at all — e.g.
            // a lyrics-only provider being asked to generate music. This is
            // a guaranteed failure, so skip it immediately instead of
            // wasting a retry/backoff cycle on it.
            if (! in_array($operation, $provider->supportedOperations(), true)) {
                Log::info("AI Router: skipping {$providerModel->slug} — does not support operation [{$operation}].");
                continue;
            }

            $result = $this->tryProviderWithRetries($provider, $providerModel, $operation, $request, $userId);

            if ($result !== null) {
                return $result;
            }

            // tryProviderWithRetries returning null means: move to next provider.
        }

        Log::error('AI Router: all providers exhausted.', ['attempted' => $attempted]);

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
    ): mixed {
        $attempt = 0;

        while ($attempt <= self::MAX_RETRIES_PER_PROVIDER) {
            $attempt++;
            $startedAt = microtime(true);

            try {
                $response = $this->invoke($provider, $operation, $request);

                $this->recordSuccess($providerModel, $userId, $operation, $startedAt);

                return $response;
            } catch (RateLimitException $e) {
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::RateLimited);

                if ($e->retryAfterSeconds !== null && $e->retryAfterSeconds <= 5 && $attempt <= self::MAX_RETRIES_PER_PROVIDER) {
                    usleep($e->retryAfterSeconds * 1_000_000);
                    continue;
                }

                Log::info("AI Router: {$providerModel->slug} rate limited — moving to next provider.");
                return null; // move on
            } catch (QuotaExceededException $e) {
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::QuotaExhausted);

                Log::info("AI Router: {$providerModel->slug} quota exceeded — moving to next provider.");
                return null; // never retry this provider again today
            } catch (TemporaryProviderException $e) {
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);

                if ($attempt <= self::MAX_RETRIES_PER_PROVIDER) {
                    $this->backoff($attempt);
                    continue;
                }

                $this->markStatus($providerModel, ProviderStatus::Error);
                Log::info("AI Router: {$providerModel->slug} still failing after retries — moving to next provider.");
                return null;
            } catch (ProviderException $e) {
                // Auth errors and any other provider-specific failure.
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::Error);

                Log::warning("AI Router: {$providerModel->slug} failed ({$e->getMessage()}) — moving to next provider.");
                return null;
            } catch (Throwable $e) {
                // Never let an unexpected exception bubble up with internal details.
                $this->recordFailure($providerModel, $userId, $operation, $e, $startedAt);
                $this->markStatus($providerModel, ProviderStatus::Error);

                Log::error("AI Router: unexpected error from {$providerModel->slug}: {$e->getMessage()}");
                return null;
            }
        }

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
}
