<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStreak extends Model
{
    protected $fillable = [
        'user_id',
        'current_streak',
        'longest_streak',
        'last_checkin_date',
    ];

    protected $casts = [
        'last_checkin_date' => 'date',
    ];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->updated_at = now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
