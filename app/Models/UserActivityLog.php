<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];
}
