<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiEventLog extends Model
{
    protected $fillable = [
        'user_id',
        'intent',
        'emotion',
        'safety_triggered'
    ];

    protected $casts = [
        'safety_triggered' => 'boolean'
    ];
}
