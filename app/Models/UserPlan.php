<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPlan extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'plan_type', 'coach_id',
        'session_id', 'file_url', 'plan_data',
        'generated_at', 'language', 'version',
    ];

    protected $casts = [
        'plan_data'    => 'array',
        'generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    /**
     * Get the latest plan of a given type for a user.
     */
    public static function latestForUser(int $userId, string $planType): ?self
    {
        return static::where('user_id', $userId)
            ->where('plan_type', $planType)
            ->orderByDesc('version')
            ->orderByDesc('generated_at')
            ->first();
    }

    /**
     * Get the next version number for a user+type combination.
     */
    public static function nextVersion(int $userId, string $planType): int
    {
        $max = static::where('user_id', $userId)
            ->where('plan_type', $planType)
            ->max('version');

        return ($max ?? 0) + 1;
    }
}
