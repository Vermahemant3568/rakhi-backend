<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
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

        $user->loadMissing(['goals']);

        $msg     = trim(strtolower($currentMessage));
        $msgType = $this->classifyMessage($msg, $sessionId);

        // Greeting: only history, zero DB/vector calls
        if ($msgType === 'greeting') {
            return [
                'recent_history'    => $this->getRecentHistory($sessionId),
                'memories'          => [],
                'knowledge'         => [],
                'structured_memory' => [],
                'checkin'           => null,
                'meals_today'       => [],
                'existing_plans'    => [],
            ];
        }

        // Structured memory — always fetch for non-greetings
        $structuredMemory = [];
        try {
            $structuredMemory = $this->memory->getStructuredMemory($user);
        } catch (\Exception $e) {
            Log::warning('Memory failed: ' . $e->getMessage());
        }

        // Vector calls only for complex messages
        $memories  = [];
        $knowledge = [];

        if ($msgType === 'complex') {
            try {
                // Short-term: recent session context (last 24h)
                $shortTerm = $this->memory->recall($user, $currentMessage, limit: 2, type: 'short_term');
                // Long-term: older relevant memories
                $longTerm  = $this->memory->recall($user, $currentMessage, limit: 2, type: 'long_term');
                // Merge: short-term first (more relevant), deduplicate
                $memories  = array_values(array_unique(array_merge($shortTerm, $longTerm)));
            } catch (\Exception $e) {}

            try {
                $knowledge = $this->memory->recallCoachKnowledge($coachNamespace, $currentMessage, limit: 1);
            } catch (\Exception $e) {}
        }

        return [
            'recent_history'    => $this->getRecentHistory($sessionId),
            'memories'          => $this->trimArray($memories),
            'knowledge'         => $this->trimArray($knowledge),
            'structured_memory' => $this->compactStructured($structuredMemory, $msg),
            'checkin'           => $msgType === 'complex' ? $this->getTodayCheckin($user->id) : null,
            'meals_today'       => $this->isMealRelated($msg) ? $this->getTodayMeals($user->id) : [],
            'existing_plans'    => $this->isPlanRelated($msg) ? $this->getExistingPlans($user->id) : [],
        ];
    }

    // ─────────────────────────────────────────
    // MESSAGE CLASSIFIER
    // Returns: 'greeting' | 'simple' | 'complex'
    // ─────────────────────────────────────────

    private function classifyMessage(string $msg, int $sessionId): string
    {
        // Pure greetings and one-word acknowledgements — no context needed
        if (preg_match('/^(hi|hey|hello|ok|okay|thanks|thank you|haan|hmm|yes|no|k|sure|got it|will try|noted|nice|great|good|cool|fine|theek|acha|accha|bilkul|shukriya|👍|😊|🙏)[\s!.]*$/', $msg)) {
            return 'greeting';
        }

        // Short follow-up acknowledgements with no new info
        if (strlen($msg) < 25 && preg_match('/^(okay will|sure will|i will|let me try|trying|understood|will do|sounds good|makes sense)/', $msg)) {
            return 'greeting';
        }

        // Follow-up answer detection — short replies that answer Rakhi's previous question
        // e.g. "abhi saam se", "kal se", "2 din se", "since yesterday", "1 hafte se"
        if ($this->isFollowUpAnswer($msg, $sessionId)) {
            return 'followup';
        }

        // Health keywords that always need full context
        $healthKeywords = [
            'sugar', 'blood', 'weight', 'sleep', 'stress', 'diet', 'eat', 'food',
            'exercise', 'tired', 'pain', 'medicine', 'thyroid', 'pcos', 'diabetes',
            'energy', 'mood', 'anxiety', 'period', 'pregnancy', 'insulin', 'bp',
            'cholesterol', 'vitamin', 'protein', 'calories', 'workout', 'gym',
            'numb', 'tingling', 'swelling', 'burning', 'cramp', 'weakness',
            'fever', 'headache', 'dizzy', 'nausea', 'vomit', 'breathe',
            'dard', 'pero', 'pair', 'haath', 'pet', 'sar', 'seena', 'kamar',
            'khana', 'khaana', 'neend', 'thakan', 'dawai', 'tablet', 'injection',
            'sujan', 'jalan', 'khujli', 'kamzori', 'chakkar', 'bukhaar', 'ulti',
            'sans', 'dil', 'aankhein', 'peeshab', 'peshab', 'pyaas', 'bhookh',
            'motapa', 'vajan', 'periods', 'mahavari', 'garbh', 'sugar level',
        ];

        foreach ($healthKeywords as $kw) {
            if (str_contains($msg, $kw)) {
                return 'complex';
            }
        }

        if (strlen($msg) < 40) {
            return 'simple';
        }

        return 'complex';
    }

    /**
     * Detect if this short message is a follow-up answer to Rakhi's last question.
     * Checks two things:
     * 1. The message looks like a time/duration/quantity answer
     * 2. Rakhi's last message ended with a question
     */
    private function isFollowUpAnswer(string $msg, int $sessionId): bool
    {
        // Time/duration/quantity patterns that are typical follow-up answers
        $followUpPatterns = [
            // Duration answers: "2 din se", "kal se", "saam se", "subah se", "1 hafte se"
            '/\b(se|since|from|ago|pehle|pahle)\b/',
            // Time of day answers
            '/\b(saam|subah|raat|dopahar|morning|evening|night|afternoon|abhi|kal|aaj|parso)\b/',
            // Number + unit answers: "2 din", "3 ghante", "1 week", "do hafte"
            '/\b(\d+|ek|do|teen|char|paanch)\s*(din|ghante|hafte|mahine|week|month|hour|day|minute)\b/',
            // Yes/no with detail: "haan bahut", "nahi itna", "thoda sa"
            '/^(haan|nahi|nai|ha|na)\s+\w+/',
            // Single word quantity answers
            '/^(bahut|thoda|zyada|kam|bilkul|kabhi kabhi|aksar|hamesha|rarely|sometimes|always|never)[\s!.]*$/',
        ];

        $isFollowUpLike = false;
        foreach ($followUpPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                $isFollowUpLike = true;
                break;
            }
        }

        if (!$isFollowUpLike) {
            return false;
        }

        // Confirm Rakhi's last message was a question
        $lastRakhiMessage = ChatMessage::where('session_id', $sessionId)
            ->where('role', 'rakhi')
            ->orderBy('created_at', 'desc')
            ->value('message');

        if (!$lastRakhiMessage) {
            return false;
        }

        // Check if last Rakhi message ended with a question mark (any language)
        return str_contains($lastRakhiMessage, '?');
    }

    private function isMealRelated(string $msg): bool
    {
        return (bool) preg_match('/\b(eat|food|meal|diet|breakfast|lunch|dinner|snack|khana|khaana|roti|rice|dal|sabzi|calories|protein|carb|fat|nutrition)\b/', $msg);
    }

    private function isPlanRelated(string $msg): bool
    {
        return (bool) preg_match('/\b(plan|routine|schedule|program|suggest|recommend|what should i|how should i|mujhe batao|kya karna chahiye|kya khana chahiye)\b/', $msg);
    }

    // ─────────────────────────────────────────
    // HISTORY
    // ─────────────────────────────────────────

    public function buildRecentHistoryOnly(int $sessionId): array
    {
        return $this->getRecentHistory($sessionId);
    }

    private function getRecentHistory(int $sessionId): array
    {
        return ChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role'    => $m->role,
                'message' => $this->shorten($m->message),
            ])
            ->toArray();
    }

    public function getLastAiResponse(int $sessionId): string
    {
        return ChatMessage::where('session_id', $sessionId)
            ->where('role', 'rakhi')
            ->orderBy('created_at', 'desc')
            ->value('message') ?? '';
    }

    // ─────────────────────────────────────────
    // STRUCTURED MEMORY — priority filter
    // ─────────────────────────────────────────

    private function compactStructured(array $memory, string $msg = ''): array
    {
        if (empty($memory)) return [];

        // Always include — core identity of the user
        $alwaysInclude = ['health_condition', 'main_goal', 'diabetes_type', 'medications', 'lifestyle'];

        // Include when message is topically relevant
        $conditional = [
            'diet_habit'     => $this->isMealRelated($msg),
            'food_preference'=> $this->isMealRelated($msg),
            'activity_level' => str_contains($msg, 'exercise') || str_contains($msg, 'workout')
                                || str_contains($msg, 'walk')   || str_contains($msg, 'gym')
                                || str_contains($msg, 'active') || str_contains($msg, 'fitness'),
            'sleep_pattern'  => str_contains($msg, 'sleep')  || str_contains($msg, 'neend')
                                || str_contains($msg, 'tired') || str_contains($msg, 'rest'),
            'stress_level'   => str_contains($msg, 'stress') || str_contains($msg, 'tension')
                                || str_contains($msg, 'anxiety') || str_contains($msg, 'pressure'),
            'challenges'     => strlen($msg) > 30, // include for any substantive message
            'family_context' => str_contains($msg, 'family') || str_contains($msg, 'husband')
                                || str_contains($msg, 'wife')  || str_contains($msg, 'kids')
                                || str_contains($msg, 'ghar'),
        ];

        $result = [];

        foreach ($memory as $k => $v) {
            if (empty($v)) continue;

            if (in_array($k, $alwaysInclude)) {
                $result[$k] = $this->shorten($v, 100);
                continue;
            }

            if (isset($conditional[$k]) && $conditional[$k]) {
                $result[$k] = $this->shorten($v, 100);
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────
    // TOKEN HELPERS
    // ─────────────────────────────────────────

    private function shorten(string $text, int $limit = 300): string
    {
        $text = trim($text);
        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }

    private function trimArray(array $items): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                return array_map(fn($v) => is_string($v) ? $this->shorten($v) : $v, $item);
            }
            return is_string($item) ? $this->shorten($item) : $item;
        }, $items);
    }

    // ─────────────────────────────────────────
    // CHECKIN / MEALS / PLANS
    // ─────────────────────────────────────────

    private function getTodayCheckin(int $userId): ?array
    {
        $c = DailyCheckin::where('user_id', $userId)
            ->where('checkin_date', today())
            ->first();

        if (!$c) return null;

        return [
            'mood'   => $c->mood,
            'energy' => $c->energy_level,
            'sleep'  => $c->sleep_hours,
        ];
    }

    private function getTodayMeals(int $userId): array
    {
        return MealLog::where('user_id', $userId)
            ->where('logged_date', today())
            ->take(2)
            ->get()
            ->map(fn($m) => [
                'meal' => $m->meal_name,
                'time' => $m->meal_time,
            ])
            ->toArray();
    }

    private function getExistingPlans(int $userId): array
    {
        return UserPlan::where('user_id', $userId)
            ->latest()
            ->take(2)
            ->get()
            ->map(fn($p) => ['type' => $p->plan_type])
            ->toArray();
    }
}
