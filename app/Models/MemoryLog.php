<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MemoryLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'summary',
        'pinecone_id',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function policy()
    {
        return $this->belongsTo(MemoryPolicy::class, 'type', 'type');
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
