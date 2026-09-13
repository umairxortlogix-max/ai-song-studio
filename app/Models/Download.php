<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'song_id', 'format', 'created_at'];

    protected static function booted(): void
    {
        static::creating(function (Download $d) {
            $d->created_at = $d->created_at ?: now();
        });
    }
}
