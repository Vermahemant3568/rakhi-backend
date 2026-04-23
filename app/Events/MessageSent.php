<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $sessionId,
        public string $role,
        public string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('chat.' . $this->sessionId);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'role'       => $this->role,
            'message'    => $this->message,
            'created_at' => now()->toISOString(),
        ];
    }
}
