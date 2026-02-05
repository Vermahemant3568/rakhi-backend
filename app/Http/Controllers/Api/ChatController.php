<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Coach\CoachService;
use App\Services\Memory\MemoryManager;
use App\Services\Memory\MemorySelectorService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $user = $request->user();
        $user->load('profile'); // Load user profile

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

        // Use the complete Coach flow (now with memory injection)
        $coachService = new CoachService();
        $aiReply = $coachService->processMessage($user, $request->message);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'rakhi',
            'message' => $aiReply,
            'intent' => $intent,
            'emotion' => $emotion
        ]);

        // Store user message as memory for future context
        $memoryManager = new MemoryManager();
        $memoryManager->storeMemory(
            $user->id,
            $this->determineMemoryType($request->message),
            $request->message,
            [
                'conversation_id' => $conversation->id,
                'intent' => $intent,
                'emotion' => $emotion
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'reply' => $aiReply,
                'intent' => $intent,
                'emotion' => $emotion
            ]
        ]);
    }

    public function testChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        try {
            $user = $request->user();
            
            // Use the enhanced Coach reasoning engine
            $coachService = new CoachService();
            $reply = $coachService->processMessage($user, $request->message);
            
            // Get debug info from coach decision engine
            $coachDecisionEngine = new \App\Services\Coach\CoachDecisionEngine();
            $analysis = $coachDecisionEngine->processUserInput($user, $request->message);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $reply,
                    'debug_info' => [
                        'intent' => $analysis['intent'],
                        'emotion' => $analysis['emotion'],
                        'goals_count' => count($analysis['user_goals']),
                        'memories_found' => count($analysis['memories']),
                        'coach_decision' => $analysis['coach_decision']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine memory type based on message content
     */
    private function determineMemoryType(string $message): string
    {
        $messageLower = strtolower($message);
        
        // Simple keyword-based classification
        if (preg_match('/\b(eat|food|meal|lunch|dinner|breakfast|hungry)\b/', $messageLower)) {
            return 'food';
        }
        
        if (preg_match('/\b(workout|exercise|gym|run|walk|fitness)\b/', $messageLower)) {
            return 'exercise';
        }
        
        if (preg_match('/\b(feel|mood|sad|happy|angry|stressed|emotion)\b/', $messageLower)) {
            return 'mood';
        }
        
        if (preg_match('/\b(goal|target|achieve|progress|success)\b/', $messageLower)) {
            return 'goal';
        }
        
        if (preg_match('/\b(habit|routine|daily|regular|practice)\b/', $messageLower)) {
            return 'habit';
        }
        
        return 'general';
    }
}