<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserMemory;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Support\Facades\Log;

class GenerateHealthPackage
{
    public function __construct(
        public User $user,
        public int  $sessionId,
        public string $lang = 'en'
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        try {
            $user   = User::with(['goals', 'language', 'coaches'])->findOrFail($this->user->id);
            $lang   = $this->lang;
            $isHindi = str_starts_with($lang, 'hi') || $lang === 'hi-roman';

            $goals         = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
            $userMemory    = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
            $memoryContext = collect($userMemory)
                ->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)
                ->join("\n");

            $conversation = ChatMessage::where('session_id', $this->sessionId)
                ->orderBy('id')
                ->get()
                ->map(fn($m) => ucfirst($m->role) . ': ' . $m->message)
                ->join("\n");

            // ── 1. Generate Diet Plan ─────────────────────────────────────────
            $dietPlan = $this->generateDietPlan($llm, $user, $goals, $memoryContext, $conversation, $isHindi);

            // ── 2. Generate Fitness Plan ──────────────────────────────────────
            $fitnessPlan = $this->generateFitnessPlan($llm, $user, $goals, $memoryContext, $conversation, $isHindi);

            // ── 3. Generate Consultation Report ──────────────────────────────
            $report = $this->generateReport($llm, $user, $goals, $memoryContext, $conversation, $userMemory, $isHindi);

            // ── Sync memory → user profile fields ────────────────────────────
            $this->syncMemoryToProfile($user, $userMemory);

            // ── 4. Generate combined PDF ──────────────────────────────────────
            $fileUrl = $pdf->generateHealthPackage($user, $dietPlan, $fitnessPlan, $report, $userMemory, $lang);

            // ── 5. Save to user_plans ─────────────────────────────────────────
            $coachId = $user->primaryCoach()?->id ?? 1;

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'health_package',
                'coach_id'     => $coachId,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'language'     => $lang,
                'plan_data'    => [
                    'diet'     => $dietPlan,
                    'fitness'  => $fitnessPlan,
                    'report'   => $report,
                ],
                'generated_at' => now(),
            ]);

            // ── 6. Deliver PDF to chat (single message, no text plan dump) ────
            $notifyMsg = $isHindi
                ? "Aapka personalized health plan taiyaar hai! 🌸\n\nNeeche apni report download karein."
                : "Your personalized health plan is ready. 🌸\n\nPlease download your report below.";

            ChatMessage::create([
                'session_id'   => $this->sessionId,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $notifyMsg,
                'message_type' => 'pdf',
                'file_url'     => $fileUrl,
            ]);

            // ── 7. Transition to active_coaching ONLY after PDF is delivered ──
            $user->update([
                'first_consultation_complete' => true,
                'consultation_state'          => 'active_coaching',
            ]);

            // Mark session as no longer first consultation
            ChatSession::where('id', $this->sessionId)
                ->update(['is_first_consultation' => false]);

            Log::info("Health package generated and delivered for user {$user->id}");

        } catch (\Exception $e) {
            Log::error('GenerateHealthPackage failed for user ' . $this->user->id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DIET PLAN
    // ─────────────────────────────────────────────────────────────────────────

    private function generateDietPlan(
        LLMRouter $llm, User $user, string $goals,
        string $memoryContext, string $conversation, bool $isHindi
    ): array {
        $langNote = $isHindi
            ? 'Write all meal names, descriptions, and tips in Hindi (Devanagari script). Use Indian food.'
            : 'Write in English. Use Indian food options.';

        $prompt = "You are Rakhi, an expert Indian nutritionist.
Create a detailed 7-day personalized diet plan for:
Name: {$user->first_name} | Age: {$user->getAge()} yrs | Weight: {$user->weight} kg | Height: {$user->height} cm
Diet preference: {$user->diet_preference} | Goals: {$goals} | Activity: {$user->activity_level}

USER HEALTH PROFILE:
{$memoryContext}

CONSULTATION CONVERSATION:
{$conversation}

{$langNote}

Return ONLY valid JSON:
{
  \"daily_targets\": {\"calories\": 0, \"protein\": 0, \"carbs\": 0, \"fat\": 0},
  \"meals\": [{\"time\": \"Breakfast\", \"name\": \"meal name\", \"description\": \"what to eat\", \"calories\": 0}],
  \"tips\": [\"tip1\", \"tip2\", \"tip3\"]
}
Return ONLY JSON. No extra text.";

        $response = $llm->chat($prompt);
        $plan     = $this->parseJson($response);

        return $plan ?? $this->fallbackDietPlan($user);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FITNESS PLAN
    // ─────────────────────────────────────────────────────────────────────────

    private function generateFitnessPlan(
        LLMRouter $llm, User $user, string $goals,
        string $memoryContext, string $conversation, bool $isHindi
    ): array {
        $langNote = $isHindi
            ? 'Write all exercise names, descriptions, and tips in Hindi (Devanagari script).'
            : 'Write in English. Make it realistic for Indians.';

        $prompt = "You are Rakhi, an expert fitness coach.
Create a 4-week progressive fitness plan for:
Name: {$user->first_name} | Age: {$user->getAge()} yrs | Weight: {$user->weight} kg
Goals: {$goals} | Activity: {$user->activity_level}

USER HEALTH PROFILE:
{$memoryContext}

CONSULTATION CONVERSATION:
{$conversation}

{$langNote}

Return ONLY valid JSON:
{
  \"weeks\": [{
    \"week\": 1, \"focus\": \"week focus\",
    \"days\": [{\"day\": \"Monday\", \"description\": \"workout\", \"exercises\": [\"ex1\"], \"duration\": 30}]
  }],
  \"tips\": [\"tip1\", \"tip2\"]
}
Return ONLY JSON. No extra text.";

        $response = $llm->chat($prompt);
        $plan     = $this->parseJson($response);

        return $plan ?? $this->fallbackFitnessPlan();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSULTATION REPORT
    // ─────────────────────────────────────────────────────────────────────────

    private function generateReport(
        LLMRouter $llm, User $user, string $goals,
        string $memoryContext, string $conversation,
        array $userMemory, bool $isHindi
    ): array {
        $recentCheckins = DailyCheckin::where('user_id', $user->id)->latest()->take(7)->get();
        $recentMeals    = MealLog::where('user_id', $user->id)->latest()->take(10)->get();

        $checkinsText = $recentCheckins->map(fn($c) =>
            "Date: {$c->checkin_date}, Mood: {$c->mood}, Energy: {$c->energy_level}/10, Sleep: {$c->sleep_hours}hrs"
        )->join("\n") ?: 'No recent check-ins';

        $mealsText = $recentMeals->map(fn($m) =>
            "{$m->meal_name} ({$m->meal_time}) — {$m->calories} kcal"
        )->join("\n") ?: 'No recent meals logged';

        $langNote = $isHindi
            ? 'Write all findings, recommendations, and next steps in Hindi (Devanagari script).'
            : 'Write in English. Be empathetic and practical.';

        $prompt = "You are Rakhi, an expert AI health coach.
Create a consultation report for:
Name: {$user->first_name} {$user->last_name} | Age: {$user->getAge()} yrs
Goals: {$goals} | Weight: {$user->weight} kg | Activity: {$user->activity_level}

USER HEALTH PROFILE:
{$memoryContext}

CONSULTATION CONVERSATION:
{$conversation}

Recent check-ins: {$checkinsText}
Recent meals: {$mealsText}

{$langNote}

Return ONLY valid JSON:
{
  \"findings\": [{\"area\": \"area name\", \"observation\": \"observation text\"}],
  \"recommendations\": [\"rec1\", \"rec2\"],
  \"next_steps\": [\"step1\", \"step2\"]
}
Return ONLY JSON. No extra text.";

        $response = $llm->chat($prompt);
        $report   = $this->parseJson($response);

        return $report ?? [
            'findings'        => [['area' => 'General Health', 'observation' => 'A personalized plan has been created based on your consultation.']],
            'recommendations' => ['Follow your personalized diet and fitness plan consistently.', 'Check in daily to track your progress.'],
            'next_steps'      => ['Start with your diet plan from tomorrow.', 'Complete your first workout this week.'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function syncMemoryToProfile(User $user, array $userMemory): void
    {
        $syncData = [];
        $fieldMap = [
            'diet_habit'     => 'diet_preference',
            'activity_level' => 'activity_level',
            'sleep_pattern'  => 'sleep_hours',
            'stress_level'   => 'stress_level',
        ];
        foreach ($fieldMap as $memKey => $userField) {
            if (!empty($userMemory[$memKey])) {
                $val = $userMemory[$memKey];
                if ($userField === 'sleep_hours') {
                    preg_match('/\d+(\.\d+)?/', $val, $m);
                    $val = $m[0] ?? $val;
                }
                $syncData[$userField] = $val;
            }
        }
        if (!empty($syncData)) {
            $user->update($syncData);
            $user->refresh();
        }
    }

    private function parseJson(string $raw): ?array
    {
        $clean = preg_replace('/^```[a-z]*\s*/m', '', $raw);
        $clean = preg_replace('/^```\s*$/m', '', $clean);
        $clean = trim($clean);
        $start = strpos($clean, '{');
        $end   = strrpos($clean, '}');
        if ($start === false || $end === false) return null;
        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function fallbackDietPlan(User $user): array
    {
        $calories = match(strtolower($user->activity_level ?? '')) {
            'high', 'very active' => 2200,
            'moderate', 'medium'  => 1900,
            default               => 1600,
        };
        return [
            'daily_targets' => ['calories' => $calories, 'protein' => 80, 'carbs' => 220, 'fat' => 55],
            'meals' => [
                ['time' => 'Early Morning', 'name' => 'Warm water + almonds',       'description' => '1 glass warm water with lemon, 5 soaked almonds', 'calories' => 50],
                ['time' => 'Breakfast',     'name' => 'Poha / Upma',                'description' => '1.5 cups poha with vegetables', 'calories' => 300],
                ['time' => 'Mid Morning',   'name' => 'Seasonal fruit',             'description' => '1 apple or banana', 'calories' => 80],
                ['time' => 'Lunch',         'name' => 'Dal + Roti + Sabzi + Salad', 'description' => '2 rotis, 1 bowl dal, 1 bowl sabzi, salad', 'calories' => 500],
                ['time' => 'Evening',       'name' => 'Chai + Snack',               'description' => '1 cup low-sugar chai, roasted chana', 'calories' => 150],
                ['time' => 'Dinner',        'name' => 'Light Dal + Roti',           'description' => '1-2 rotis, 1 bowl dal, 1 bowl sabzi', 'calories' => 450],
            ],
            'tips' => [
                'Eat dinner at least 2 hours before sleeping',
                'Drink 8-10 glasses of water daily',
                'Avoid fried and packaged foods',
            ],
        ];
    }

    private function fallbackFitnessPlan(): array
    {
        return [
            'weeks' => [
                ['week' => 1, 'focus' => 'Getting Started', 'days' => [
                    ['day' => 'Monday',    'description' => 'Morning walk',         'exercises' => ['20 min brisk walk'], 'duration' => 20],
                    ['day' => 'Tuesday',   'description' => 'Stretching & yoga',    'exercises' => ['20 min yoga'],       'duration' => 20],
                    ['day' => 'Wednesday', 'description' => 'Rest',                 'exercises' => ['Rest'],              'duration' => 0],
                    ['day' => 'Thursday',  'description' => 'Walk',                 'exercises' => ['25 min walk'],       'duration' => 25],
                    ['day' => 'Friday',    'description' => 'Bodyweight',           'exercises' => ['10 squats', '10 push-ups'], 'duration' => 20],
                    ['day' => 'Saturday',  'description' => 'Active rest',          'exercises' => ['30 min leisure walk'], 'duration' => 30],
                    ['day' => 'Sunday',    'description' => 'Full rest',            'exercises' => ['Rest'],              'duration' => 0],
                ]],
            ],
            'tips' => ['Start slow — consistency matters more than intensity', 'Drink water before and after every workout'],
        ];
    }
}
