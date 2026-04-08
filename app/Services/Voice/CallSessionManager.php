<?php

namespace App\Services\Voice;

use App\Models\ChatSession;
use App\Models\User;

class CallSessionManager
{
    public function hasActiveSession(User $user): bool
    {
        return ChatSession::where('user_id', $user->id)
            ->where('session_type', 'voice')
            ->where('status', 'active')
            ->exists();
    }

    public function getActiveSession(User $user): ?ChatSession
    {
        return ChatSession::where('user_id', $user->id)
            ->where('session_type', 'voice')
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    public function closeOldSessions(User $user): void
    {
        ChatSession::where('user_id', $user->id)
            ->where('session_type', 'voice')
            ->where('status', 'active')
            ->update([
                'status'   => 'closed',
                'ended_at' => now(),
            ]);
    }
}
