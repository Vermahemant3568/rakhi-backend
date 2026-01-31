<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'trial_start',
        'trial_end',
        'current_period_start',
        'current_period_end',
        'payment_provider',
        'provider_subscription_id'
    ];
}
