<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceSession;
use App\Services\Voice\VoiceConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceCallController extends Controller
{
    public function __construct(
        private VoiceConversationService $voiceService
    ) {}

    public function start(Request $request)
    {
        try {
            $user = $request->user()->load('profile', 'goals');
            
            $session = VoiceSession::create([
                'user_id' => $user->id,
                'status' => 'started',
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => ['session_id' => $session->id]
            ]);
        } catch (\Exception $e) {
            Log::error('Voice session start error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json([
                'success' => false,
                'error' => 'Unable to start voice session'
            ], 500);
        }
    }

    public function end(Request $request, int $id)
    {
        try {
            $session = VoiceSession::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $session->update([
                'status' => 'ended',
                'ended_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call ended successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Voice session end error', ['error' => $e->getMessage(), 'session_id' => $id]);
            return response()->json([
                'success' => false,
                'error' => 'Unable to end voice session'
            ], 500);
        }
    }

    public function processAudio(Request $request)
    {
        $validated = $request->validate([
            'audio_chunk' => 'required|string',
            'session_id' => 'required|integer|exists:voice_sessions,id'
        ]);

        try {
            $user = $request->user()->load('profile', 'goals');
            
            $session = VoiceSession::where('id', $validated['session_id'])
                ->where('user_id', $user->id)
                ->where('status', 'started')
                ->firstOrFail();

            $this->voiceService->initialize($user, $session);
            $audioResponse = $this->voiceService->handleAudioChunk($validated['audio_chunk']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'audio_response' => $audioResponse,
                    'should_terminate' => $this->voiceService->shouldTerminateCall()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Voice audio processing error', [
                'error' => $e->getMessage(),
                'session_id' => $validated['session_id'] ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Unable to process audio'
            ], 500);
        }
    }
}