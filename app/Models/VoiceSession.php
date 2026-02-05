<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceSession extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'started_at',
        'ended_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'voice_session_id');
    }
}
