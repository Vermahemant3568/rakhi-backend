<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    public $timestamps = false;

    // The DB uses started_at (not created_at/updated_at)
    // Tell Eloquent to use started_at for ordering via ->latest()
    const CREATED_AT = 'started_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'coach_id', 'session_type',
        'is_first_consultation', 'status', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'is_first_consultation' => 'boolean',
        'started_at'            => 'datetime',
        'ended_at'              => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }
}
