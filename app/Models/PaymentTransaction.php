<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'payment_provider',
        'provider_payment_id',
        'provider_subscription_id',
        'status'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}