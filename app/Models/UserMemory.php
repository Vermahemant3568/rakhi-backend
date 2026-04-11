<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMemory extends Model
{
    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = ['user_id', 'key', 'value', 'source'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    // All valid memory keys Rakhi can store
    public const KEYS = [
        'health_condition', // diabetes, PCOS, thyroid, BP, etc.
        'diet_habit',       // what they eat daily
        'diet_timing',      // meal timing patterns
        'activity_level',   // exercise, walking, sedentary
        'sleep_pattern',    // hours and quality
        'stress_level',     // stress triggers and intensity
        'main_goal',        // weight loss, sugar control, etc.
        'food_preference',  // veg/non-veg, likes/dislikes
        'lifestyle',        // work schedule, busy, WFH, etc.
        'challenges',       // what makes it hard for them
        'medications',      // any medicines they mentioned
        'family_context',   // relevant family info (kids, spouse)
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
