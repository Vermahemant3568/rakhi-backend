<?php

namespace App\Services\Voice;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CallSessionManager
{
    // Sessions active longer than this are considered stuck/abandoned
    private const MAX_SESSION_MINUTES = 60;

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
        $sessions = ChatSession::where('user_id', $user->id)
            ->where('session_type', 'voice')
            ->where('status', 'active')
            ->get();

        foreach ($sessions as $session) {
            $session->update([
                'status'   => 'closed',
                'ended_at' => now(),
            ]);

            Log::info('Closed stale voice session', [
                'session_id' => $session->id,
                'user_id'    => $user->id,
                'started_at' => $session->started_at,
            ]);
        }
    }

    // Close sessions stuck active beyond MAX_SESSION_MINUTES (cron-safe)
    public function closeAbandonedSessions(): int
    {
        $cutoff = now()->subMinutes(self::MAX_SESSION_MINUTES);

        $count = ChatSession::where('session_type', 'voice')
            ->where('status', 'active')
            ->where('started_at', '<', $cutoff)
            ->update([
                'status'   => 'closed',
                'ended_at' => now(),
            ]);

        if ($count > 0) {
            Log::info("Closed {$count} abandoned voice sessions older than " . self::MAX_SESSION_MINUTES . " minutes");
        }

        return $count;
    }

    public function getDurationSeconds(ChatSession $session): int
    {
        if (!$session->started_at) return 0;
        $end = $session->ended_at ?? now();
        return (int) $session->started_at->diffInSeconds($end);
    }
}
