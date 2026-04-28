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
        'is_first_consultation', 'status', 'detected_language',
        'detected_script', 'started_at', 'ended_at',
        'parent_chat_session_id', 'unified_session_id',
        'call_failed_count', 'stt_fail_count',
        'call_invite_pending', 'voice_fallback_active',
    ];

    protected $casts = [
        'is_first_consultation'  => 'boolean',
        'call_invite_pending'    => 'boolean',
        'voice_fallback_active'  => 'boolean',
        'started_at'             => 'datetime',
        'ended_at'               => 'datetime',
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

    /**
     * All sessions in the same unified conversation thread (voice + chat).
     */
    public function unifiedSessions()
    {
        return $this->hasMany(ChatSession::class, 'unified_session_id', 'unified_session_id')
            ->where('user_id', $this->user_id);
    }

    /**
     * All messages across the entire unified conversation thread.
     */
    public function unifiedMessages()
    {
        $rootId = $this->unified_session_id ?? $this->id;

        $sessionIds = ChatSession::where('unified_session_id', $rootId)
            ->where('user_id', $this->user_id)
            ->pluck('id');

        // If no sessions found via unified_session_id, fall back to just this session
        if ($sessionIds->isEmpty()) {
            $sessionIds = collect([$this->id]);
        }

        return ChatMessage::whereIn('session_id', $sessionIds)
            ->where('user_id', $this->user_id)
            ->orderBy('created_at', 'asc');
    }
}
