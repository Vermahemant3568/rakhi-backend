<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    public $timestamps = false;

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
