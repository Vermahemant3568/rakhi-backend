<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Support\Facades\Log;

class GenerateConsultationReport
{
    public function __construct(
        public User $user,
        public int $sessionId
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        try {
            $user  = User::with(['goals', 'coaches'])->findOrFail($this->user->id);
            $goals = $user->goals->pluck('name')->join(', ') ?: 'general wellness';

            $conversationContext = ChatMessage::where('session_id', $this->sessionId)
                ->orderBy('id')
                ->get()
                ->map(fn($m) => ucfirst($m->role) . ': ' . $m->message)
                ->join("\n");

            $recentCheckins = DailyCheckin::where('user_id', $user->id)
                ->latest()->take(7)->get();

            $recentMeals = MealLog::where('user_id', $user->id)
                ->latest()->take(10)->get();

            $checkinsText = $recentCheckins->map(fn($c) =>
                "Date: {$c->checkin_date}, Mood: {$c->mood}, Energy: {$c->energy_level}/10, Sleep: {$c->sleep_hours}hrs"
            )->join("\n") ?: 'No recent check-ins';

            $mealsText = $recentMeals->map(fn($m) =>
                "{$m->meal_name} ({$m->meal_time}) — {$m->calories} kcal"
            )->join("\n") ?: 'No recent meals logged';

            $userMemory = \App\Models\UserMemory::where('user_id', $user->id)
                ->pluck('value', 'key')
                ->toArray();

            $memoryContext = collect($userMemory)
                ->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)
                ->join("\n");

            $prompt = "You are Rakhi, an expert AI health coach.
Create a consultation report for:
Name: {$user->first_name} {$user->last_name}
Age: {$user->getAge()} years
Goals: {$goals}
Weight: {$user->weight} kg
Activity: {$user->activity_level}

USER MEMORY (structured facts from consultation):
{$memoryContext}

CONVERSATION CONTEXT (Welcome Consultation):
{$conversationContext}

Recent check-ins (last 7 days):
{$checkinsText}

Recent meals:
{$mealsText}

Return ONLY valid JSON:
{
  \"findings\": [
    {
      \"area\": \"area name\",
      \"observation\": \"what you observed from conversation and data\"
    }
  ],
  \"recommendations\": [
    \"recommendation 1\",
    \"recommendation 2\"
  ],
  \"next_steps\": [
    \"next step 1\",
    \"next step 2\"
  ]
}

Be empathetic, specific, and practical. Reference insights from the conversation.
Return ONLY JSON. No extra text.";

            $response = $llm->chat($prompt);
            $report   = $this->parseJson($response);

            if (!$report) {
                // Fallback so PDF still generates even if LLM returns bad JSON
                $report = [
                    'findings'        => [['area' => 'General Health', 'observation' => 'Based on your consultation, a personalized plan has been created for you.']],
                    'recommendations' => ['Follow your personalized diet and fitness plan consistently.', 'Check in daily to track your progress.'],
                    'next_steps'      => ['Start with your diet plan from tomorrow.', 'Complete your first workout this week.'],
                ];
            }

            // Sync memory values back to user profile fields so PDF shows real data
            $fieldMap = [
                'diet_habit'     => 'diet_preference',
                'activity_level' => 'activity_level',
                'sleep_pattern'  => 'sleep_hours',
                'stress_level'   => 'stress_level',
            ];
            $syncData = [];
            foreach ($fieldMap as $memKey => $userField) {
                if (!empty($userMemory[$memKey])) {
                    $val = $userMemory[$memKey];
                    // For sleep_hours extract numeric value if present
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

            $fileUrl = $pdf->generateConsultationReport($user, $report, $userMemory);

            $coachId = $user->primaryCoach()?->id ?? 1;

            UserPlan::create([
                'user_id'    => $user->id,
                'plan_type'  => 'consultation',
                'coach_id'   => $coachId,
                'session_id' => $this->sessionId,
                'file_url'   => $fileUrl,
                'plan_data'  => $report,
            ]);

            $deliveryMessages = [
                "Your Health Consultation Report is ready! 📋\n\nI've put together a detailed report based on everything you shared — your habits, goals, and what your body needs. Take a look when you're ready. 🌸",
                "Your consultation report is all done! 📋\n\nIt covers everything we talked about — your lifestyle, your goals, and what I think will work best for you. Have a read when you get a moment 🌸",
                "I've finished your health report! 📋\n\nEverything you shared has been put together into a personalised report just for you. Take a look whenever you're ready 🌸",
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

            Log::info("Consultation report generated for user {$user->id}");

        } catch (\Exception $e) {
            Log::error('Consultation report generation failed for user ' . $this->user->id . ': ' . $e->getMessage());
            // Do not re-throw — a failed report should not block diet/fitness plan delivery
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
            Log::warning('GenerateConsultationReport JSON decode error: ' . json_last_error_msg());
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }
}
