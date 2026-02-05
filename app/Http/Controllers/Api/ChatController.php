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
        $user->load('profile', 'goals'); // Load user profile and goals

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

        // Store onboarding data as memories if first conversation
        if ($conversation->wasRecentlyCreated) {
            $onboardingService = new \App\Services\Memory\OnboardingMemoryService();
            $onboardingService->storeOnboardingData($user);
        }

        // Use the new CoachPromptBuilder for complete context integration
        $promptBuilder = new \App\Services\Coach\CoachPromptBuilder();
        $fullPrompt = $promptBuilder->build($user, $request->message);
        
        // Get AI response with complete context
        $aiService = new \App\Services\AI\AiService();
        $aiReply = $aiService->reply($fullPrompt);

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

        // Store Rakhi's reply as memory if it contains meaningful information
        if ($this->isMeaningfulReply($aiReply)) {
            $memoryManager->storeMemory(
                $user->id,
                'coaching_advice',
                $aiReply,
                [
                    'conversation_id' => $conversation->id,
                    'response_to' => $request->message,
                    'type' => 'ai_response'
                ]
            );
        }

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
     * Check if Rakhi's reply contains meaningful information worth storing
     */
    private function isMeaningfulReply(string $reply): bool
    {
        $replyLower = strtolower($reply);
        
        // Store replies that contain advice, suggestions, or specific information
        $meaningfulKeywords = [
            'suggest', 'recommend', 'try', 'should', 'could', 'advice',
            'exercise', 'workout', 'eat', 'food', 'goal', 'plan',
            'remember', 'important', 'tip', 'help', 'improve'
        ];
        
        foreach ($meaningfulKeywords as $keyword) {
            if (str_contains($replyLower, $keyword)) {
                return true;
            }
        }
        
        // Don't store generic greetings or short responses
        return strlen($reply) > 50;
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