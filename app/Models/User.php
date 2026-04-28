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
        'first_consultation_complete', 'consultation_state',
        'plan_generation_state',
        'notification_enabled', 'microphone_enabled',
        'camera_enabled', 'fcm_token', 'last_active_at',
        'engagement_state', 'last_message_at',
        'escalation_call_count', 'last_escalation_at',
    ];

    protected $hidden = ['remember_token'];

    protected $casts = [
        'date_of_birth'               => 'date',
        'onboarding_complete'         => 'boolean',
        'first_consultation_complete' => 'boolean',
        'consultation_state'          => 'string',
        'notification_enabled'        => 'boolean',
        'microphone_enabled'          => 'boolean',
        'camera_enabled'              => 'boolean',
        'is_active'                   => 'boolean',
        'is_banned'                   => 'boolean',
        'last_active_at'              => 'datetime',
        'last_message_at'             => 'datetime',
        'last_escalation_at'          => 'datetime',
        'escalation_call_count'       => 'integer',
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
    public function isInConsultation(): bool
    {
        return $this->consultation_state !== null
            && in_array($this->consultation_state, ['pending', 'in_consultation']);
    }

    public function isActiveCoaching(): bool
    {
        return $this->consultation_state === 'active_coaching';
    }

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

    // ── Plan generation state helpers ─────────────────────────────────────────

    public function isPlanGenerating(): bool
    {
        return $this->plan_generation_state === 'generating';
    }

    public function isPlanCompleted(): bool
    {
        return $this->plan_generation_state === 'completed';
    }

    public function isPlanFailed(): bool
    {
        return $this->plan_generation_state === 'failed';
    }

    public function setPlanState(string $state): void
    {
        // Valid states: collecting_data | ready_to_generate | generating | completed | failed
        $this->update(['plan_generation_state' => $state]);
    }

    // ── Engagement state helpers ───────────────────────────────────────────────

    public function isEngagementActive(): bool
    {
        return ($this->engagement_state ?? 'active') === 'active';
    }

    public function isSlowResponse(): bool
    {
        return $this->engagement_state === 'slow_response';
    }

    public function isNonResponsive(): bool
    {
        return $this->engagement_state === 'non_responsive';
    }
}
