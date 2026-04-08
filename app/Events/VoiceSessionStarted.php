<?php

namespace App\Events;

use App\Models\ChatSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class VoiceSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ChatSession $session) {}

    public function broadcastOn(): Channel
    {
        return new Channel('voice.' . $this->session->user_id);
    }

    public function broadcastAs(): string
    {
        return 'voice.started';
    }
}
