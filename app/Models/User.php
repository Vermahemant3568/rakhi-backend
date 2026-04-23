<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'first_name', 'last_name', 'mobile', 'email',
        'gender', 'date_of_birth', 'weight', 'height',
        'language_id', 'profile_photo', 'activity_level',
        'stress_level', 'sleep_hours', 'diet_preference',
        'is_active', 'is_banned', 'ban_reason',
        'onboarding_step', 'onboarding_complete',
        'first_consultation_complete',
        'notification_enabled', 'microphone_enabled',
        'camera_enabled', 'fcm_token', 'last_active_at',
    ];

    protected $hidden = ['remember_token'];

    protected $casts = [
        'date_of_birth'               => 'date',
        'onboarding_complete'         => 'boolean',
        'first_consultation_complete' => 'boolean',
        'notification_enabled'        => 'boolean',
        'microphone_enabled'          => 'boolean',
        'camera_enabled'              => 'boolean',
        'is_active'                   => 'boolean',
        'is_banned'                   => 'boolean',
        'last_active_at'              => 'datetime',
    ];

    // JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => 'user'];
    }

    // Relationships
    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function goals()
    {
        return $this->belongsToMany(Goal::class, 'user_goals')
                    ->wherePivot('is_active', 1);
    }

    public function coaches()
    {
        return $this->belongsToMany(Coach::class, 'user_coaches');
    }

    public function primaryCoach()
    {
        return $this->belongsToMany(Coach::class, 'user_coaches')
                    ->wherePivot('is_primary', 1)
                    ->first();
    }

    public function subscription()
    {
        return $this->hasOne(UserSubscription::class)
                    ->latest();
    }

    public function streak()
    {
        return $this->hasOne(UserStreak::class);
    }

    public function dailyCheckins()
    {
        return $this->hasMany(DailyCheckin::class);
    }

    public function mealLogs()
    {
        return $this->hasMany(MealLog::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function userPlans()
    {
        return $this->hasMany(UserPlan::class);
    }

    // Helpers
    public function isSubscribed(): bool
    {
        $sub = $this->subscription;
        return $sub && in_array($sub->status, ['trial', 'active'])
               && $sub->ends_at > now();
    }

    public function getAge(): int
    {
        return $this->date_of_birth
            ? $this->date_of_birth->age
            : 0;
    }
}
