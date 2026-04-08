<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCoach extends Pivot
{
    protected $table = 'user_coaches';

    protected $fillable = [
        'user_id',
        'coach_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public $timestamps = false;
}
