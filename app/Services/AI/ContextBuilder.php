<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserPlan;
use App\Services\Vector\UserMemoryService;
use Illuminate\Support\Facades\Log;

class ContextBuilder
{
    public function __construct(
        private UserMemoryService $memory
    ) {}

    public function build(
        User $user,
        string $currentMessage,
        int $sessionId,
        string $coachNamespace
    ): array {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $memories         = [];
        $knowledge        = [];
        $structuredMemory = [];

        // Skip expensive Pinecone calls for short/simple messages (greetings, ack, one-liners)
        $isSimpleMessage = strlen($currentMessage) < 30
            || preg_match('/^(hi|hey|hello|ok|okay|thanks|haan|nahi|yes|no|sure|hmm|k|👍|😊)$/i', trim($currentMessage));

        try {
            $structuredMemory = $this->memory->getStructuredMemory($user);
        } catch (\Exception $e) {
            Log::warning('Structured memory fetch failed (non-fatal): ' . $e->getMessage());
        }

        if (!$isSimpleMessage) {
            try {
                $memories = $this->memory->recall($user, $currentMessage, limit: 3);
            } catch (\Exception $e) {
                Log::warning('Memory recall failed (non-fatal): ' . $e->getMessage());
            }

            try {
                $knowledge = $this->memory->recallCoachKnowledge($coachNamespace, $currentMessage, limit: 2);
            } catch (\Exception $e) {
                Log::warning('Knowledge recall failed (non-fatal): ' . $e->getMessage());
            }
        }

        return [
            'recent_history'     => $this->buildRecentHistoryOnly($sessionId),
            'cross_session_msgs' => $this->getCrossSessionHistory($user->id, $sessionId),
            'memories'           => $memories,
            'knowledge'          => $knowledge,
            'structured_memory'  => $structuredMemory,
            'checkin'            => $this->getTodayCheckin($user->id),
            'meals_today'        => $this->getTodayMeals($user->id),
            'existing_plans'     => $this->getExistingPlans($user->id),
            'consultation_notes' => $this->getConsultationNotes($user->id),
        ];
    }

    // Safe fallback used when Pinecone is unavailable
    public function buildRecentHistoryOnly(int $sessionId): array
    {
        return $this->getRecentHistory($sessionId);
    }

    private function getRecentHistory(int $sessionId): array
    {
        return ChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->take(8)
            ->get()
            ->map(fn($m) => [
                'role'    => $m->role,
                'message' => $m->message,
            ])
            ->toArray();
    }

    /**
     * Pull key messages from previous sessions so Rakhi remembers
     * what the user shared across different conversations.
     */
    private function getCrossSessionHistory(int $userId, int $currentSessionId): array
    {
        // Get last 3 previous sessions
        $previousSessions = ChatSession::where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->orderBy('id', 'desc')
            ->take(3)
            ->pluck('id');

        if ($previousSessions->isEmpty()) return [];

        // Pull last 2 messages from each previous session (Rakhi's responses only — most informative)
        return ChatMessage::whereIn('session_id', $previousSessions)
            ->where('role', 'rakhi')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(fn($m) => [
                'role'    => $m->role,
                'message' => substr($m->message, 0, 300),
            ])
            ->toArray();
    }

    /**
     * Get the user's consultation notes from their first consultation session.
     * This is the richest source of user context — their goals, routine, diet, challenges.
     */
    private function getConsultationNotes(int $userId): array
    {
        $consultationSession = ChatSession::where('user_id', $userId)
            ->where('session_type', 'chat')
            ->orderBy('id', 'asc')
            ->first();

        if (!$consultationSession) return [];

        return ChatMessage::where('session_id', $consultationSession->id)
            ->where('role', 'user')
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get()
            ->map(fn($m) => $m->message)
            ->toArray();
    }

    private function getTodayCheckin(int $userId): ?array
    {
        $checkin = DailyCheckin::where('user_id', $userId)
            ->where('checkin_date', today())
            ->first();

        return $checkin ? [
            'mood'         => $checkin->mood,
            'energy_level' => $checkin->energy_level,
            'sleep_hours'  => $checkin->sleep_hours,
            'water_intake' => $checkin->water_intake,
        ] : null;
    }

    private function getTodayMeals(int $userId): array
    {
        return MealLog::where('user_id', $userId)
            ->where('logged_date', today()->toDateString())
            ->get()
            ->map(fn($m) => [
                'meal'     => $m->meal_name,
                'time'     => $m->meal_time,
                'calories' => $m->calories,
            ])
            ->toArray();
    }

    /**
     * Get user's existing plans so Rakhi knows what she already created.
     */
    private function getExistingPlans(int $userId): array
    {
        return UserPlan::where('user_id', $userId)
            ->orderBy('generated_at', 'desc')
            ->take(3)
            ->get()
            ->map(fn($p) => [
                'type'         => $p->plan_type,
                'generated_at' => $p->generated_at?->format('d M Y'),
            ])
            ->toArray();
    }
}
