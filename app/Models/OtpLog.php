<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpLog extends Model
{
    protected $fillable = ['phone', 'otp', 'is_used', 'expires_at'];

    protected $casts = [
        'is_used'    => 'boolean',
        'expires_at' => 'datetime',
    ];
}
