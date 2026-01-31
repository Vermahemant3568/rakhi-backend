<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\NLP\IntentService;
use App\Services\NLP\EmotionService;
use App\Services\AI\GeminiService;
use App\Services\Memory\MemoryReader;
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

        $intent = (new IntentService())->detect($request->message);
        $emotion = (new EmotionService())->detect($request->message);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $request->message,
            'intent' => $intent,
            'emotion' => $emotion
        ]);

        // Recall relevant memories
        $memories = (new MemoryReader())->recall($request->message);
        
        $memoryContext = collect($memories)
            ->pluck('metadata.summary')
            ->implode("\n");

        $aiReply = (new GeminiService())->reply($request->message, $memoryContext);

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