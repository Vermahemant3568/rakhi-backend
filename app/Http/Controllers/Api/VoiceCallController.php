<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceSession;
use Illuminate\Http\Request;

class VoiceCallController extends Controller
{
    public function start(Request $request)
    {
        $session = VoiceSession::create([
            'user_id' => $request->user()->id,
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
}
