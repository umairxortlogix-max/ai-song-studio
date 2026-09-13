<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'provider_id', 'generation_id', 'operation', 'model',
        'tokens_used', 'credits_used', 'status', 'error_message', 'response_time', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (AiUsageLog $log) {
            $log->created_at = $log->created_at ?: now();
        });
    }

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
