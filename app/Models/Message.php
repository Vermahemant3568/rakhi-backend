<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'voice_session_id',
        'sender',
        'message',
        'intent',
        'emotion'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function voiceSession()
    {
        return $this->belongsTo(VoiceSession::class);
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }
}