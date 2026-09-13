<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderFailure extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider_id', 'operation', 'exception_class', 'message', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (ProviderFailure $f) {
            $f->created_at = $f->created_at ?: now();
        });
    }
}
