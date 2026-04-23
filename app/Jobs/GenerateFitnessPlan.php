<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Support\Facades\Log;

class GenerateFitnessPlan
{
    public function __construct(
        public User $user,
        public int $sessionId
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        try {
            $user  = User::with(['goals'])->findOrFail($this->user->id);
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

            $prompt = "You are Rakhi, an expert fitness coach.
Create a 4-week progressive fitness plan for:
Name: {$user->first_name}
Age: {$user->getAge()} years
Weight: {$user->weight} kg
Goals: {$goals}
Activity level: {$user->activity_level}

USER MEMORY (from consultation):
{$memoryContext}

CONSULTATION CONVERSATION:
{$conversationContext}

Return ONLY valid JSON:
{
  \"weeks\": [
    {
      \"week\": 1,
      \"focus\": \"week focus\",
      \"days\": [
        {
          \"day\": \"Monday\",
          \"description\": \"workout description\",
          \"exercises\": [\"exercise1\", \"exercise2\"],
          \"duration\": 30
        }
      ]
    }
  ],
  \"tips\": [\"tip1\", \"tip2\"]
}

Include rest days. Make it realistic for Indians.
Return ONLY JSON. No extra text.";

            $response = $llm->chat($prompt);
            $planData = $this->parseJson($response);

            if (!$planData) {
                Log::warning('Fitness plan LLM returned invalid JSON, using fallback for user ' . $user->id);
                $planData = $this->fallbackFitnessPlan($user);
            }

            $fileUrl = $pdf->generateFitnessPlan($user, $planData);

            $coachId = $user->primaryCoach()?->id ?? 1;

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'fitness',
                'coach_id'     => $coachId,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'plan_data'    => $planData,
                'generated_at' => now(),
            ]);

            $deliveryMessages = [
                "Your personalized Fitness Plan is ready! 💪\n\nI've built a 4-week progressive plan around your goals and current activity level. Start at your own pace — consistency is what matters most. 🌸",
                "Your fitness plan is all set! 💪\n\nIt's a 4-week plan built around where you are right now and where you want to get to. Take it one week at a time 🌸",
                "Here's your 4-week fitness plan! 💪\n\nI've designed it to match your current activity level and build up gradually. No pressure — just start when you're ready 🌸",
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

            Log::info("Fitness plan generated for user {$user->id}");

        } catch (\Exception $e) {
            Log::error('Fitness plan generation failed for user ' . $this->user->id . ': ' . $e->getMessage());
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
            Log::warning('GenerateFitnessPlan JSON decode error: ' . json_last_error_msg());
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private function fallbackFitnessPlan(User $user): array
    {
        $isLowActivity = in_array(strtolower($user->activity_level ?? ''), ['low', 'sedentary', '']);
        return [
            'weeks' => [
                ['week' => 1, 'focus' => 'Getting Started — Build the habit', 'days' => [
                    ['day' => 'Monday',    'description' => 'Morning walk',          'exercises' => ['20 min brisk walk'],                          'duration' => 20],
                    ['day' => 'Tuesday',   'description' => 'Stretching & yoga',     'exercises' => ['10 min stretching', '10 min basic yoga'],       'duration' => 20],
                    ['day' => 'Wednesday', 'description' => 'Rest day',              'exercises' => ['Light walking if possible'],                   'duration' => 0],
                    ['day' => 'Thursday',  'description' => 'Morning walk',          'exercises' => ['25 min brisk walk'],                          'duration' => 25],
                    ['day' => 'Friday',    'description' => 'Bodyweight exercises',  'exercises' => ['10 squats', '10 wall push-ups', '20 sec plank'], 'duration' => 20],
                    ['day' => 'Saturday',  'description' => 'Active rest',           'exercises' => ['30 min leisure walk or cycling'],              'duration' => 30],
                    ['day' => 'Sunday',    'description' => 'Full rest',             'exercises' => ['Rest and recover'],                            'duration' => 0],
                ]],
                ['week' => 2, 'focus' => 'Building Consistency', 'days' => [
                    ['day' => 'Monday',    'description' => 'Walk + squats',         'exercises' => ['25 min walk', '15 squats'],                    'duration' => 30],
                    ['day' => 'Tuesday',   'description' => 'Yoga',                  'exercises' => ['20 min yoga flow'],                            'duration' => 20],
                    ['day' => 'Wednesday', 'description' => 'Rest',                  'exercises' => ['Rest'],                                        'duration' => 0],
                    ['day' => 'Thursday',  'description' => 'Cardio walk',           'exercises' => ['30 min brisk walk'],                          'duration' => 30],
                    ['day' => 'Friday',    'description' => 'Strength basics',       'exercises' => ['15 squats', '10 push-ups', '30 sec plank'],    'duration' => 25],
                    ['day' => 'Saturday',  'description' => 'Active day',            'exercises' => ['Cycling or swimming 30 min'],                  'duration' => 30],
                    ['day' => 'Sunday',    'description' => 'Rest',                  'exercises' => ['Rest'],                                        'duration' => 0],
                ]],
                ['week' => 3, 'focus' => 'Increasing Intensity', 'days' => [
                    ['day' => 'Monday',    'description' => 'Cardio + core',         'exercises' => ['30 min walk/jog', '20 crunches'],              'duration' => 35],
                    ['day' => 'Tuesday',   'description' => 'Yoga + stretching',     'exercises' => ['25 min yoga'],                                 'duration' => 25],
                    ['day' => 'Wednesday', 'description' => 'Rest',                  'exercises' => ['Rest'],                                        'duration' => 0],
                    ['day' => 'Thursday',  'description' => 'Full body workout',     'exercises' => ['20 squats', '15 push-ups', '1 min plank'],     'duration' => 30],
                    ['day' => 'Friday',    'description' => 'Cardio',                'exercises' => ['35 min brisk walk or jog'],                    'duration' => 35],
                    ['day' => 'Saturday',  'description' => 'Active rest',           'exercises' => ['Outdoor activity of choice'],                  'duration' => 30],
                    ['day' => 'Sunday',    'description' => 'Rest',                  'exercises' => ['Rest'],                                        'duration' => 0],
                ]],
                ['week' => 4, 'focus' => 'Maintaining & Progressing', 'days' => [
                    ['day' => 'Monday',    'description' => 'Cardio',                'exercises' => ['40 min walk/jog'],                             'duration' => 40],
                    ['day' => 'Tuesday',   'description' => 'Strength',              'exercises' => ['25 squats', '20 push-ups', '1.5 min plank'],   'duration' => 30],
                    ['day' => 'Wednesday', 'description' => 'Yoga',                  'exercises' => ['30 min yoga'],                                 'duration' => 30],
                    ['day' => 'Thursday',  'description' => 'Rest',                  'exercises' => ['Rest'],                                        'duration' => 0],
                    ['day' => 'Friday',    'description' => 'Full body',             'exercises' => ['30 squats', '20 push-ups', '20 crunches'],     'duration' => 35],
                    ['day' => 'Saturday',  'description' => 'Long walk',             'exercises' => ['45 min outdoor walk'],                         'duration' => 45],
                    ['day' => 'Sunday',    'description' => 'Rest',                  'exercises' => ['Rest and recover'],                            'duration' => 0],
                ]],
            ],
            'tips' => [
                'Start slow — consistency matters more than intensity',
                'Drink water before and after every workout',
                'Warm up for 5 minutes before any exercise',
                'Listen to your body — rest when needed',
            ],
        ];
    }
}
