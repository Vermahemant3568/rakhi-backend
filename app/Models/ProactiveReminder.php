<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProactiveReminder extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'sent_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'reminder_type', 'habit_key',
        'sent_at', 'user_responded', 'responded_at',
    ];

    protected $casts = [
        'sent_at'        => 'datetime',
        'responded_at'   => 'datetime',
        'user_responded' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
