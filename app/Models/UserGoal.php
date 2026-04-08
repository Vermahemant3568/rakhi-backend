<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserGoal extends Pivot
{
    protected $table = 'user_goals';

    protected $fillable = [
        'user_id',
        'goal_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $timestamps = false;
}
