<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCheckin extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'mood', 'energy_level', 'sleep_hours',
        'water_intake', 'exercise_done', 'notes', 'checkin_date',
    ];

    protected $casts = [
        'checkin_date'  => 'date',
        'exercise_done' => 'boolean',
        'created_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
