<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserUsage extends Model
{
    protected $table = 'user_usage';

    protected $fillable = ['user_id', 'date', 'generations_count'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
