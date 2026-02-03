<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mobile',
        'country_code',
        'mobile_verified_at',
        'is_active',
        'is_onboarded',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_onboarded' => 'boolean',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'user_languages');
    }

    public function goals()
    {
        return $this->belongsToMany(Goal::class, 'user_goals');
    }

    public function subscription()
    {
        return $this->hasOne(UserSubscription::class);
    }
}