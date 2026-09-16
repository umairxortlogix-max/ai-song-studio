<?php

namespace App\Console\Commands;

use App\Models\AiProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * php artisan ai:reset-usage
 *
 * Resets `used_today` at midnight and `used_this_month` on the 1st.
 * Uses a cache flag so it only resets once per day/month even if the
 * scheduler fires this command frequently.
 */
class ResetProviderUsage extends Command
{
    protected $signature = 'ai:reset-usage';
    protected $description = 'Reset daily/monthly provider usage counters at the correct boundaries';

    public function handle(): int
    {
        $today = now()->toDateString();

        if (Cache::add("ai_usage_reset_daily:{$today}", true, now()->endOfDay())) {
            AiProvider::query()->update(['used_today' => 0]);

            // Clearing the counter is not enough: isQuotaExhaustedLocally() also skips on
            // status, so a provider that hit its quota once stayed skipped forever.
            AiProvider::query()
                ->whereIn('status', ['quota_exhausted', 'rate_limited'])
                ->update(['status' => 'healthy', 'failure_count' => 0]);

            $this->info('Daily provider usage counters reset.');
        }

        if (now()->day === 1) {
            $month = now()->format('Y-m');
            if (Cache::add("ai_usage_reset_monthly:{$month}", true, now()->endOfMonth())) {
                AiProvider::query()->update(['used_this_month' => 0]);
                AiProvider::query()
                    ->where('status', 'quota_exhausted')
                    ->update(['status' => 'healthy', 'failure_count' => 0]);
                $this->info('Monthly provider usage counters reset.');
            }
        }

        return self::SUCCESS;
    }
}
