<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginAttempt extends Model
{
    protected $fillable = [
        'email',
        'ip_address',
        'attempted_at',
        'successful'
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'successful' => 'boolean'
    ];
}