<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealLog extends Model
{
    protected $fillable = [
        'user_id',
        'image_path',
        'meal_name',
        'calories',
        'protein',
        'carbs',
        'fat',
        'analysis_data',
        'logged_at',
    ];

    protected $casts = [
        'analysis_data' => 'array',
        'logged_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
