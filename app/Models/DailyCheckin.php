<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'mood',
        'energy',
        'diet_followed',
        'activity_done'
    ];

    protected $casts = [
        'date' => 'date',
        'diet_followed' => 'boolean',
        'activity_done' => 'boolean'
    ];
}
