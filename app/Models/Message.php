<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender',
        'message',
        'intent',
        'emotion'
    ];

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }
}