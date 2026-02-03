<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryPolicy extends Model
{
    protected $fillable = [
        'type',
        'description',
        'is_active',
        'priority',
        'retention_days'
    ];
}