<?php

namespace App\WebSockets;

use App\Services\Voice\VoiceConversationService;
use App\Models\VoiceSession;

class VoiceStreamHandler
{
    protected VoiceConversationService $voiceConversationService;
    protected ?int $sessionId = null;

    public function __construct()
    {
        $this->voiceConversationService = new VoiceConversationService();
    }

    public function setSessionId(int $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function handle($audioChunk, $connection)
    {
        $rakhiAudio = $this->voiceConversationService->handleAudioChunk($audioChunk);

        if ($rakhiAudio) {
            // Send raw PCM audio bytes back via WebSocket
            $connection->send($rakhiAudio);
        }

        // Check if call should be terminated
        if ($this->voiceConversationService->shouldTerminateCall() && $this->sessionId) {
            $this->endSession();
            $connection->close();
        }

        return $rakhiAudio;
    }

    protected function endSession()
    {
        if ($this->sessionId) {
            VoiceSession::where('id', $this->sessionId)->update([
                'status' => 'ended',
                'ended_at' => now()
            ]);
        }
    }

    public function close()
    {
        $this->voiceConversationService->close();
        $this->endSession();
    }
}