<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = [
        'name', 'slug', 'provider_type', 'api_key', 'api_base_url', 'model',
        'is_active', 'priority', 'daily_limit', 'monthly_limit',
        'used_today', 'used_this_month', 'last_used_at', 'failure_count', 'status',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'api_key' => 'encrypted', // never stored/shown in plaintext
        ];
    }

    public function usageLogs()
    {
        return $this->hasMany(AiUsageLog::class, 'provider_id');
    }

    public function failures()
    {
        return $this->hasMany(ProviderFailure::class, 'provider_id');
    }

    public function usagePercent(): int
    {
        if (! $this->daily_limit) {
            return 0;
        }

        return (int) min(100, round(($this->used_today / max(1, $this->daily_limit)) * 100));
    }
}
