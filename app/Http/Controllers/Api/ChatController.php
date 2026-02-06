<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Coach\CoachService;
use App\Services\Memory\OnboardingMemoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        private CoachService $coachService,
        private OnboardingMemoryService $onboardingService
    ) {}

    public function send(Request $request)
    {
        $validated = $request->validate(['message' => 'required|string']);

        try {
            $user = $request->user()->load('profile', 'goals');
            $conversation = $this->getOrCreateConversation($user->id);

            if ($conversation->wasRecentlyCreated) {
                $this->onboardingService->storeOnboardingData($user);
            }

            $this->storeUserMessage($conversation->id, $validated['message']);
            $reply = $this->coachService->processMessage($user, $validated['message']);
            $this->storeRakhiMessage($conversation->id, $reply);

            return response()->json([
                'success' => true,
                'data' => ['reply' => $reply]
            ]);
        } catch (\Exception $e) {
            Log::error('Chat send error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json([
                'success' => false,
                'error' => 'Unable to process message. Please try again.'
            ], 500);
        }
    }

    public function testChat(Request $request)
    {
        $validated = $request->validate(['message' => 'required|string']);

        try {
            $user = $request->user()->load('profile', 'goals');
            $reply = $this->coachService->processMessage($user, $validated['message']);

            return response()->json([
                'success' => true,
                'data' => ['reply' => $reply]
            ]);
        } catch (\Exception $e) {
            Log::error('Test chat error', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getOrCreateConversation(int $userId): Conversation
    {
        return Conversation::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active']
        );
    }

    private function storeUserMessage(int $conversationId, string $message): void
    {
        Message::create([
            'conversation_id' => $conversationId,
            'sender' => 'user',
            'message' => $message,
            'intent' => 'general',
            'emotion' => 'neutral'
        ]);
    }

    private function storeRakhiMessage(int $conversationId, string $message): void
    {
        Message::create([
            'conversation_id' => $conversationId,
            'sender' => 'rakhi',
            'message' => $message,
            'intent' => 'general',
            'emotion' => 'neutral'
        ]);
    }
}