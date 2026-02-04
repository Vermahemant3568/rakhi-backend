<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Coach\CoachService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $user = $request->user();

        $conversation = Conversation::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active']
        );

        // Simple intent and emotion detection
        $intent = 'general';
        $emotion = 'neutral';

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $request->message,
            'intent' => $intent,
            'emotion' => $emotion
        ]);

        // Use the complete Coach flow
        $coachService = new CoachService();
        $aiReply = $coachService->processMessage($user, $request->message);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'rakhi',
            'message' => $aiReply,
            'intent' => $intent,
            'emotion' => $emotion
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'reply' => $aiReply,
                'intent' => $intent,
                'emotion' => $emotion
            ]
        ]);
    }
}