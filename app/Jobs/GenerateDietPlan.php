<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Support\Facades\Log;

class GenerateDietPlan
{
    public function __construct(
        public User $user,
        public int $sessionId
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        try {
            $user  = User::with(['goals', 'language'])->findOrFail($this->user->id);
            $goals = $user->goals->pluck('name')->join(', ') ?: 'general wellness';

            $conversationContext = ChatMessage::where('session_id', $this->sessionId)
                ->orderBy('id')
                ->get()
                ->map(fn($m) => ucfirst($m->role) . ': ' . $m->message)
                ->join("\n");

            $userMemory = \App\Models\UserMemory::where('user_id', $user->id)
                ->pluck('value', 'key')
                ->toArray();

            $memoryContext = collect($userMemory)
                ->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)
                ->join("\n");

            $prompt = "You are Rakhi, an expert Indian nutritionist.
Create a detailed 7-day personalized diet plan for:
Name: {$user->first_name}
Age: {$user->getAge()} years
Weight: {$user->weight} kg
Height: {$user->height} cm
Diet preference: {$user->diet_preference}
Goals: {$goals}
Activity level: {$user->activity_level}

USER MEMORY (from consultation):
{$memoryContext}

CONSULTATION CONVERSATION:
{$conversationContext}

Return ONLY a valid JSON object:
{
  \"daily_targets\": {
    \"calories\": 0,
    \"protein\": 0,
    \"carbs\": 0,
    \"fat\": 0
  },
  \"meals\": [
    {
      \"time\": \"breakfast\",
      \"name\": \"meal name\",
      \"description\": \"what to eat\",
      \"calories\": 0
    }
  ],
  \"tips\": [\"tip1\", \"tip2\", \"tip3\"]
}

Use Indian food options. Be realistic and practical.
Return ONLY JSON. No extra text.";

            $response  = $llm->chat($prompt);
            $planData  = $this->parseJson($response);

            if (!$planData) {
                Log::warning('Diet plan LLM returned invalid JSON, using fallback plan for user ' . $user->id);
                $planData = $this->fallbackDietPlan($user);
            }

            $fileUrl = $pdf->generateDietPlan($user, $planData);

            $coachId = $user->primaryCoach()?->id ?? 1;

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'diet',
                'coach_id'     => $coachId,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'plan_data'    => $planData,
                'generated_at' => now(),
            ]);

            $deliveryMessages = [
                "Your personalized Diet Plan is ready! 🥗\n\nI've put together a 7-day plan based on everything you shared with me — your goals, your routine, and what works for your lifestyle. Download it below and let's get started! 💪",
                "Your diet plan is done! 🥗\n\nI've built it around your goals and what you told me about your eating habits. It's practical, realistic, and made just for you. Take a look! 🌸",
                "Here's your 7-day diet plan! 🥗\n\nEvery meal in here is based on what you shared — your lifestyle, your preferences, and your goals. Start whenever you feel ready 💪",
            ];
            $msg = $deliveryMessages[(int)(microtime(true) * 1000) % 3];

            ChatMessage::create([
                'session_id'   => $this->sessionId,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $msg,
                'message_type' => 'pdf',
                'file_url'     => $fileUrl,
            ]);

            Log::info("Diet plan generated for user {$user->id}");

        } catch (\Exception $e) {
            Log::error('Diet plan generation failed for user ' . $this->user->id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    private function parseJson(string $raw): ?array
    {
        // Strip markdown code fences: ```json ... ``` or ``` ... ```
        $clean = preg_replace('/^```[a-z]*\s*/m', '', $raw);
        $clean = preg_replace('/^```\s*$/m', '', $clean);
        $clean = trim($clean);
        $start = strpos($clean, '{');
        $end   = strrpos($clean, '}');
        if ($start === false || $end === false) return null;
        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('GenerateDietPlan JSON decode error: ' . json_last_error_msg());
            return null;
        }
        return is_array($decoded) ? $decoded : null;
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
                ['time' => 'Early Morning', 'name' => 'Warm water + soaked almonds', 'description' => '1 glass warm water with lemon, 5 soaked almonds', 'calories' => 50],
                ['time' => 'Breakfast',     'name' => 'Poha / Upma',                 'description' => '1.5 cups poha with vegetables or upma with peanuts', 'calories' => 300],
                ['time' => 'Mid Morning',   'name' => 'Fruit',                       'description' => '1 seasonal fruit (apple/banana/guava)', 'calories' => 80],
                ['time' => 'Lunch',         'name' => 'Dal + Roti + Sabzi + Salad',  'description' => '2 rotis, 1 bowl dal, 1 bowl sabzi, cucumber salad', 'calories' => 500],
                ['time' => 'Evening',       'name' => 'Chai + Snack',                'description' => '1 cup low-sugar chai with 2 digestive biscuits or roasted chana', 'calories' => 150],
                ['time' => 'Dinner',        'name' => 'Light Dal + Roti / Rice',     'description' => '1-2 rotis or 1 small bowl rice, 1 bowl dal, 1 bowl sabzi', 'calories' => 450],
            ],
            'tips' => [
                'Eat dinner at least 2 hours before sleeping',
                'Drink 8-10 glasses of water daily',
                'Avoid fried and packaged foods',
                'Include a rainbow of vegetables in your meals',
            ],
        ];
    }
}
