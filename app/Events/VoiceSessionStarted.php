<?php

namespace App\Events;

use App\Models\ChatSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ShouldBroadcastNow — broadcasts synchronously, bypasses the queue.
 * This prevents Pusher broadcast jobs from clogging the database queue
 * when Pusher is not configured or unavailable.
 */
class VoiceSessionStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ChatSession $session) {}

    public function broadcastOn(): Channel
    {
        return new Channel('voice.' . ($this->session->user_id ?? 0));
    }

    public function broadcastAs(): string
    {
        return 'voice.started';
    }

    public function broadcastWith(): array
    {
        if (!$this->session->user_id) return [];

        return [
            'voice_session_id'       => $this->session->id,
            'user_id'                => $this->session->user_id,
            'parent_chat_session_id' => $this->session->parent_chat_session_id,
            'is_first_consultation'  => (bool) $this->session->is_first_consultation,
            'started_at'             => $this->session->started_at?->toISOString(),
        ];
    }
}
