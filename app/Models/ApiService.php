<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiService extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_name', 'display_name', 'config', 'is_active', 'last_tested_at',
    ];

    protected $casts = [
        'config'         => 'array',
        'is_active'      => 'boolean',
        'last_tested_at' => 'datetime',
    ];
}
