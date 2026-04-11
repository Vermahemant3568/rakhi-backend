<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    public $timestamps = false;

    // DB has created_at set by useCurrent(), no updated_at
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'user_id', 'role', 'message',
        'message_type', 'file_url', 'tokens_used',
        'llm_provider', 'coach_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
