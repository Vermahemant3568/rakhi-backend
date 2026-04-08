<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateConsultationReport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public User $user,
        public int $sessionId
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        $user = $this->user->load('goals', 'coaches');

        // Gather user data
        $recentCheckins = DailyCheckin::where('user_id', $user->id)
            ->latest()
            ->take(7)
            ->get();

        $recentMeals = MealLog::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        $goals = $user->goals->pluck('name')->join(', ');

        $checkinsText = $recentCheckins->map(fn($c) =>
            "Date: {$c->checkin_date}, Mood: {$c->mood}, " .
            "Energy: {$c->energy_level}/10, " .
            "Sleep: {$c->sleep_hours}hrs"
        )->join("\n");

        $mealsText = $recentMeals->map(fn($m) =>
            "{$m->meal_name} ({$m->meal_time}) — " .
            "{$m->calories} kcal"
        )->join("\n");

        $prompt = "You are Rakhi, an expert AI health coach.
            Create a consultation report for:
            Name: {$user->first_name} {$user->last_name}
            Age: {$user->age()} years
            Goals: {$goals}
            Weight: {$user->weight} kg
            Activity: {$user->activity_level}

            Recent checkins (last 7 days):
            {$checkinsText}

            Recent meals:
            {$mealsText}

            Return ONLY valid JSON:
            {
              'findings': [
                {
                  'area': 'area name',
                  'observation': 'what you observed'
                }
              ],
              'recommendations': [
                'recommendation 1',
                'recommendation 2'
              ],
              'next_steps': [
                'next step 1',
                'next step 2'
              ]
            }

            Be empathetic, specific, and practical.
            Return ONLY JSON. No extra text.";

        $response = $llm->chat($prompt);
        $clean    = preg_replace('/```json|```/', '', $response);
        $report   = json_decode(trim($clean), true) ?? [];

        $fileUrl = $pdf->generateConsultationReport($user, $report);

        UserPlan::create([
            'user_id'    => $user->id,
            'plan_type'  => 'consultation',
            'coach_id'   => $user->primaryCoach()?->id ?? 1,
            'session_id' => $this->sessionId,
            'file_url'   => $fileUrl,
            'plan_data'  => $report,
        ]);

        ChatMessage::create([
            'session_id'   => $this->sessionId,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => "Your Health Consultation Report is ready! 📋\n\n" .
                              "I've analysed your recent habits and " .
                              "prepared a detailed report with findings " .
                              "and recommendations just for you. 🌸",
            'message_type' => 'pdf',
            'file_url'     => $fileUrl,
        ]);
    }
}
