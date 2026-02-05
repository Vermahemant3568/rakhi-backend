<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceSession;
use App\Services\Voice\VoiceConversationService;
use Illuminate\Http\Request;

class VoiceCallController extends Controller
{
    public function start(Request $request)
    {
        $user = $request->user();
        $user->load('profile'); // Load user profile
        
        $session = VoiceSession::create([
            'user_id' => $user->id,
            'status' => 'started',
            'started_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id
            ]
        ]);
    }

    public function end(Request $request, $id)
    {
        $session = VoiceSession::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $session->update([
            'status' => 'ended',
            'ended_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Call ended'
        ]);
    }

    public function processAudio(Request $request)
    {
        $request->validate([
            'audio_chunk' => 'required|string',
            'session_id' => 'required|integer'
        ]);

        $user = $request->user();
        $user->load('profile'); // Load user profile
        
        $session = VoiceSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->where('status', 'started')
            ->firstOrFail();

        try {
            $voiceService = new VoiceConversationService($user, $session);
            $audioResponse = $voiceService->handleAudioChunk($request->audio_chunk);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'audio_response' => $audioResponse,
                    'should_terminate' => $voiceService->shouldTerminateCall()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
