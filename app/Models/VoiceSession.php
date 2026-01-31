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
}
