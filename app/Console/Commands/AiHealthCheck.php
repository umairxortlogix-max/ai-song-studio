<?php

namespace App\Console\Commands;

use App\AI\DTOs\ProviderStatus;
use App\AI\Services\ProviderResolver;
use App\Models\AiProvider;
use Illuminate\Console\Command;

/**
 * php artisan ai:health-check
 *
 * Pings every active provider's getStatus() and updates its row.
 * Scheduled to run every few minutes (see routes/console.php).
 * Providers that come back online automatically re-enter the
 * router's rotation with no manual intervention.
 */
class AiHealthCheck extends Command
{
    protected $signature = 'ai:health-check';
    protected $description = 'Check health/status of every active AI provider and update ai_providers.status';

    public function handle(ProviderResolver $resolver): int
    {
        $providers = AiProvider::where('is_active', true)->get();

        foreach ($providers as $providerModel) {
            $provider = $resolver->resolve($providerModel);

            if (! $provider) {
                $this->warn("[{$providerModel->slug}] no class resolved, skipping.");
                continue;
            }

            $status = $provider->getStatus();
            $providerModel->status = $status->value;
            $providerModel->save();

            $this->line("[{$providerModel->slug}] status: {$status->value}");
        }

        // Reset daily/monthly counters at boundaries — this command is expected
        // to run frequently; the actual reset is date-driven in ResetProviderUsage.
        $this->call('ai:reset-usage');

        return self::SUCCESS;
    }
}
