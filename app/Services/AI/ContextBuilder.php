<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Coach;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserMemory;
use App\Models\UserPlan;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\Vector\UserMemoryService;
use Illuminate\Support\Facades\Log;

class ContextBuilder
{
    public function __construct(
        private UserMemoryService $memory,
        private IntentDetector $intentDetector,
        private MoodAnalyzer $moodAnalyzer,
    ) {}

    public function build(
        User $user,
        string $currentMessage,
        int $sessionId,
        string $coachNamespace,
        ?int $parentChatSessionId = null,
        ?Coach $coach = null
    ): array {
        $user->loadMissing(['goals']);

        $msg             = trim(strtolower($currentMessage));
        $lastRakhiMsg    = $this->getLastRakhiMessage($sessionId, $parentChatSessionId);
        $msgType         = $this->intentDetector->classifyDepth($msg, $lastRakhiMsg);

        // Coach profile — always resolve
        $coachProfile = $this->buildCoachProfile($coach, $user);

        // Emotional & behavioral signals — always detect
        $emotionalContext = $this->buildEmotionalContext($currentMessage, $user->id);

        // Lightweight memory for greetings — never return empty
        if ($msgType === 'greeting') {
            $lightMemory = $this->getLightweightMemory($user->id);
            return [
                'msg_type'          => $msgType,
                'coach_profile'     => $coachProfile,
                'emotional_context' => $emotionalContext,
                'recent_history'    => $this->getRecentHistory($sessionId, $parentChatSessionId),
                'memories'          => [],
                'knowledge'         => [],
                'structured_memory' => $lightMemory,
                'checkin'           => $this->getTodayCheckin($user->id),
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

        // For follow-ups: use recent history + structured memory, skip heavy vector calls
        if ($msgType === 'follow_up') {
            return [
                'msg_type'          => $msgType,
                'coach_profile'     => $coachProfile,
                'emotional_context' => $emotionalContext,
                'recent_history'    => $this->getRecentHistory($sessionId, $parentChatSessionId),
                'memories'          => [],
                'knowledge'         => [],
                'structured_memory' => $this->compactStructured($structuredMemory, $msg),
                'checkin'           => $this->getTodayCheckin($user->id),
                'meals_today'       => [],
                'existing_plans'    => [],
            ];
        }

        // Vector calls only for complex messages
        $memories  = [];
        $knowledge = [];

        if ($msgType === 'complex') {
            try {
                $shortTerm = $this->memory->recall($user, $currentMessage, limit: 2, type: 'short_term');
                $longTerm  = $this->memory->recall($user, $currentMessage, limit: 2, type: 'long_term');
                // Condition-aware deduplication: short-term first
                $memories  = $this->deduplicateMemories(array_merge($shortTerm, $longTerm));
            } catch (\Exception $e) {}

            try {
                // Use coach namespace for condition-specific knowledge retrieval
                $ns        = $coachNamespace ?: ($coachProfile['namespace'] ?? '');
                $knowledge = $ns ? $this->memory->recallCoachKnowledge($ns, $currentMessage, limit: 1) : [];
            } catch (\Exception $e) {}
        }

        return [
            'msg_type'          => $msgType,
            'coach_profile'     => $coachProfile,
            'emotional_context' => $emotionalContext,
            'recent_history'    => $this->getRecentHistory($sessionId, $parentChatSessionId),
            'memories'          => $this->trimArray($memories),
            'knowledge'         => $this->trimArray($knowledge),
            'structured_memory' => $this->compactStructured($structuredMemory, $msg),
            'checkin'           => $this->getTodayCheckin($user->id),
            'meals_today'       => $this->isMealRelated($msg) ? $this->getTodayMeals($user->id) : [],
            'existing_plans'    => $this->isPlanRelated($msg) ? $this->getExistingPlans($user->id) : [],
        ];
    }

    // ─────────────────────────────────────────
    // COACH PROFILE BUILDER
    // Builds full coach identity: tone, behavior, scope, condition
    // ─────────────────────────────────────────

    private function buildCoachProfile(?Coach $coach, User $user): array
    {
        if (!$coach) {
            return [
                'name'        => 'Rakhi',
                'slug'        => 'general',
                'speciality'  => 'general health',
                'namespace'   => '',
                'tone'        => 'warm, empathetic, practical',
                'scope'       => 'general wellness',
                'condition'   => $this->resolveUserCondition($user),
                'stage'       => $this->resolveUserStage($user),
                'goals'       => $user->goals->pluck('name')->join(', ') ?: 'general wellness',
            ];
        }

        $toneMap = [
            'pregnancy-coach'       => 'nurturing, gentle, reassuring — like a caring elder sister',
            'pcos-thyroid-coach'    => 'validating, empathetic, science-backed — PCOS/thyroid are often dismissed, acknowledge that',
            'diabetes-coach'        => 'calm, clinical-but-warm, safety-first — diabetes management is daily and exhausting',
            'weight-loss-coach'     => 'encouraging, realistic, non-judgmental — no shame, only sustainable progress',
            'mental-wellness-coach' => 'gentle, non-directive, emotionally safe — never push, always hold space',
            'sleep-coach'           => 'calm, soothing, practical — sleep issues are frustrating, validate first',
            'energy-coach'          => 'uplifting, motivating, grounded — acknowledge fatigue before suggesting fixes',
            'stress-coach'          => 'calming, grounding, compassionate — stress is real, not weakness',
            'fitness-coach'         => 'energetic, motivating, form-focused — celebrate effort not just results',
            'diet-nutrition-coach'  => 'practical, non-restrictive, culturally aware — Indian food context always',
            'postpartum-coach'      => 'gentle, patient, non-pressuring — new mothers need grace not goals',
            'habit-coach'           => 'consistent, encouraging, systems-focused — small wins matter',
            'vision-coach'          => 'calm, informative, preventive — eye health is often neglected',
        ];

        $scopeMap = [
            'pregnancy-coach'       => 'pregnancy nutrition, safe exercise, trimester-specific guidance, symptom support',
            'pcos-thyroid-coach'    => 'hormonal health, cycle regulation, insulin resistance, thyroid management',
            'diabetes-coach'        => 'blood sugar management, meal timing, medication awareness, complication prevention',
            'weight-loss-coach'     => 'sustainable fat loss, body composition, metabolic health, behavioral change',
            'mental-wellness-coach' => 'emotional wellbeing, anxiety, stress, mindset, self-compassion',
            'sleep-coach'           => 'sleep hygiene, circadian rhythm, insomnia, sleep quality improvement',
            'energy-coach'          => 'fatigue management, nutrition for energy, activity pacing, recovery',
            'stress-coach'          => 'stress reduction, cortisol management, relaxation techniques, resilience',
            'fitness-coach'         => 'exercise programming, strength, cardio, mobility, injury prevention',
            'diet-nutrition-coach'  => 'balanced nutrition, Indian diet, meal planning, macros, micronutrients',
            'postpartum-coach'      => 'postnatal recovery, breastfeeding nutrition, gentle fitness, emotional support',
            'habit-coach'           => 'habit formation, routine building, consistency, goal tracking',
            'vision-coach'          => 'eye health, screen time, nutrition for eyes, preventive care',
        ];

        return [
            'name'        => $coach->name ?? 'Rakhi',
            'slug'        => $coach->slug ?? 'general',
            'speciality'  => $coach->speciality ?? 'general health',
            'namespace'   => $coach->pinecone_namespace ?? '',
            'tone'        => $toneMap[$coach->slug] ?? 'warm, empathetic, practical',
            'scope'       => $scopeMap[$coach->slug] ?? 'general wellness',
            'condition'   => $this->resolveUserCondition($user),
            'stage'       => $this->resolveUserStage($user),
            'goals'       => $user->goals->pluck('name')->join(', ') ?: 'general wellness',
        ];
    }

    private function resolveUserCondition(User $user): string
    {
        // Check stored memory first (most accurate)
        $memCondition = UserMemory::where('user_id', $user->id)
            ->where('key', 'health_condition')
            ->value('value');

        if ($memCondition) return $memCondition;

        // Derive from primary goal
        $primaryGoal = $user->goals->first()?->slug ?? '';
        return match($primaryGoal) {
            'manage-diabetes'      => 'diabetes',
            'manage-pcos'          => 'PCOS',
            'thyroid-management'   => 'thyroid',
            'pregnancy-wellness'   => 'pregnancy',
            'postpartum-recovery'  => 'postpartum',
            'lose-weight'          => 'weight management',
            'build-muscle'         => 'fitness',
            'improve-mental-health'=> 'mental wellness',
            'improve-sleep'        => 'sleep issues',
            'boost-energy'         => 'low energy / fatigue',
            'reduce-stress'        => 'stress management',
            default                => 'general wellness',
        };
    }

    private function resolveUserStage(User $user): string
    {
        // Check stored current_stage memory
        $stage = UserMemory::where('user_id', $user->id)
            ->where('key', 'current_stage')
            ->value('value');

        return $stage ?? '';
    }

    // ─────────────────────────────────────────
    // EMOTIONAL CONTEXT BUILDER
    // Tracks mood, energy, sleep, adherence signals
    // ─────────────────────────────────────────

    private function buildEmotionalContext(string $message, int $userId): array
    {
        $mood      = $this->moodAnalyzer->analyze($message);
        $energy    = $this->moodAnalyzer->detectEnergyLevel($message);
        $adherence = $this->moodAnalyzer->detectAdherence($message);

        // Pull stored emotional state from memory
        $storedEmotionalState = UserMemory::where('user_id', $userId)
            ->where('key', 'emotional_state')
            ->value('value');

        // Pull recent checkin for sleep/energy signals
        $checkin = DailyCheckin::where('user_id', $userId)
            ->where('checkin_date', today())
            ->first();

        return [
            'current_mood'    => $mood,
            'energy_level'    => $energy !== 'normal' ? $energy : ($checkin?->energy_level ?? 'normal'),
            'sleep_last_night'=> $checkin?->sleep_hours ?? null,
            'adherence'       => $adherence !== 'unknown' ? $adherence : null,
            'stored_state'    => $storedEmotionalState,
            'exercise_today'  => $checkin?->exercise_done ?? null,
        ];
    }

    // ─────────────────────────────────────────
    // LIGHTWEIGHT MEMORY (for greetings)
    // Returns only core identity keys — never empty
    // ─────────────────────────────────────────

    private function getLightweightMemory(int $userId): array
    {
        $coreKeys = ['health_condition', 'main_goal', 'current_stage', 'emotional_state'];

        $memory = UserMemory::where('user_id', $userId)
            ->whereIn('key', $coreKeys)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        return array_filter($memory, fn($v) => !empty($v));
    }

    // ─────────────────────────────────────────
    // HISTORY
    // ─────────────────────────────────────────

    public function buildRecentHistoryOnly(int $sessionId, ?int $parentChatSessionId = null): array
    {
        return $this->getRecentHistory($sessionId, $parentChatSessionId);
    }

    private function getRecentHistory(int $sessionId, ?int $parentChatSessionId = null): array
    {
        $session   = ChatSession::find($sessionId);
        $unifiedId = $session?->unified_session_id;

        // If unified_session_id is set, use it to pull the full cross-mode thread
        if ($unifiedId) {
            $sessionIds = ChatSession::where('unified_session_id', $unifiedId)
                ->where('user_id', $session->user_id)
                ->pluck('id')
                ->toArray();
        } else {
            // Fallback for sessions created before the unified_session_id migration
            $sessionIds = array_values(array_filter(array_unique([$sessionId, $parentChatSessionId])));
        }

        return ChatMessage::whereIn('session_id', $sessionIds)
            ->when($session?->user_id, fn($q) => $q->where('user_id', $session->user_id))
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role'    => $m->role,
                'message' => $this->shorten($m->message),
            ])
            ->values()
            ->toArray();
    }

    private function getLastRakhiMessage(int $sessionId, ?int $parentChatSessionId = null): ?string
    {
        $session   = ChatSession::find($sessionId);
        $unifiedId = $session?->unified_session_id;

        if ($unifiedId) {
            $sessionIds = ChatSession::where('unified_session_id', $unifiedId)
                ->when($session?->user_id, fn($q) => $q->where('user_id', $session->user_id))
                ->pluck('id')
                ->toArray();
        } else {
            $sessionIds = array_values(array_filter(array_unique([$sessionId, $parentChatSessionId])));
        }

        return ChatMessage::whereIn('session_id', $sessionIds)
            ->where('role', 'rakhi')
            ->orderBy('created_at', 'desc')
            ->value('message');
    }

    public function getLastAiResponse(int $sessionId, ?int $parentChatSessionId = null): string
    {
        return $this->getLastRakhiMessage($sessionId, $parentChatSessionId) ?? '';
    }

    // ─────────────────────────────────────────
    // STRUCTURED MEMORY — condition-aware priority filter
    // ─────────────────────────────────────────

    private function compactStructured(array $memory, string $msg = ''): array
    {
        if (empty($memory)) return [];

        // Always include — core identity
        $alwaysInclude = ['health_condition', 'main_goal', 'current_stage', 'diabetes_type', 'medications', 'lifestyle'];

        // Include when message is topically relevant
        $conditional = [
            'diet_habit'       => $this->isMealRelated($msg),
            'food_preference'  => $this->isMealRelated($msg),
            'activity_level'   => (bool) preg_match('/\b(exercise|workout|walk|gym|active|fitness|vyayam)\b/', $msg),
            'sleep_pattern'    => (bool) preg_match('/\b(sleep|neend|tired|rest|insomnia)\b/', $msg),
            'stress_level'     => (bool) preg_match('/\b(stress|tension|anxiety|pressure|pareshan)\b/', $msg),
            'emotional_state'  => true, // always include emotional state
            'adherence_pattern'=> true, // always include adherence
            'challenges'       => strlen($msg) > 30,
            'family_context'   => (bool) preg_match('/\b(family|husband|wife|kids|ghar|bachche)\b/', $msg),
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
    // MEMORY DEDUPLICATION
    // ─────────────────────────────────────────

    private function deduplicateMemories(array $memories): array
    {
        $seen   = [];
        $result = [];

        foreach ($memories as $mem) {
            $key = md5(strtolower(trim(is_array($mem) ? json_encode($mem) : $mem)));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[]   = $mem;
            }
        }

        return array_slice($result, 0, 4); // max 4 memories
    }

    // ─────────────────────────────────────────
    // TOPIC HELPERS
    // ─────────────────────────────────────────

    private function isMealRelated(string $msg): bool
    {
        return (bool) preg_match('/\b(eat|food|meal|diet|breakfast|lunch|dinner|snack|khana|khaana|roti|rice|dal|sabzi|calories|protein|carb|fat|nutrition)\b/', $msg);
    }

    private function isPlanRelated(string $msg): bool
    {
        return (bool) preg_match('/\b(plan|routine|schedule|program|suggest|recommend|what should i|how should i|mujhe batao|kya karna chahiye|kya khana chahiye)\b/', $msg);
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
            'mood'          => $c->mood,
            'energy'        => $c->energy_level,
            'sleep'         => $c->sleep_hours,
            'exercise_done' => $c->exercise_done,
            'notes'         => $c->notes ? $this->shorten($c->notes, 80) : null,
        ];
    }

    private function getTodayMeals(int $userId): array
    {
        return MealLog::where('user_id', $userId)
            ->where('logged_date', today())
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get()
            ->map(fn($m) => [
                'meal'     => $m->meal_name,
                'time'     => $m->created_at?->format('H:i'),
                'calories' => $m->calories,
                'protein'  => $m->protein,
            ])
            ->toArray();
    }

    private function getExistingPlans(int $userId): array
    {
        return UserPlan::where('user_id', $userId)
            ->orderByDesc('generated_at')
            ->take(2)
            ->get()
            ->map(fn($p) => ['type' => $p->plan_type])
            ->toArray();
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
}
