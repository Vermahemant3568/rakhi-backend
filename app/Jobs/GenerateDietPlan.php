<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class GenerateDietPlan implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public User $user,
        public int $sessionId
    ) {}

    public function handle(
        LLMRouter $llm,
        PDFService $pdf
    ): void {
        try {
            $user  = $this->user->load('goals', 'language');
            $goals = $user->goals->pluck('name')->join(', ');

            // Generate plan via LLM
            $prompt = "You are Rakhi, an expert Indian nutritionist.
                Create a detailed 7-day personalized diet plan for:
                Name: {$user->first_name}
                Age: {$user->age()} years
                Weight: {$user->weight} kg
                Height: {$user->height} cm
                Diet preference: {$user->diet_preference}
                Goals: {$goals}
                Activity level: {$user->activity_level}

                Return ONLY a valid JSON object:
                {
                  'daily_targets': {
                    'calories': 0,
                    'protein': 0,
                    'carbs': 0,
                    'fat': 0
                  },
                  'meals': [
                    {
                      'time': 'breakfast',
                      'name': 'meal name',
                      'description': 'what to eat',
                      'calories': 0
                    }
                  ],
                  'tips': ['tip1', 'tip2', 'tip3']
                }

                Use Indian food options. Be realistic and practical.
                Return ONLY JSON. No extra text.";

            $response = $llm->chat($prompt);

            // Clean and parse JSON
            $clean    = preg_replace('/```json|```/', '', $response);
            $clean    = trim($clean);
            $jsonStart = strpos($clean, '{');
            $jsonEnd   = strrpos($clean, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $clean = substr($clean, $jsonStart, $jsonEnd - $jsonStart + 1);
            }
            $planData = json_decode($clean, true);

            if (!$planData) {
                throw new \Exception('Invalid plan JSON from LLM: ' . substr($response, 0, 200));
            }

            // Generate PDF
            $fileUrl = $pdf->generateDietPlan($user, $planData);

            // Save plan record
            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'diet',
                'coach_id'     => 2,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'plan_data'    => $planData,
                'generated_at' => now(),
            ]);

            // Send as chat message
            ChatMessage::create([
                'session_id'   => $this->sessionId,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => "Your personalized Diet Plan is ready! 🥗\n\n" .
                                  "I've created a plan tailored to your goals " .
                                  "and Indian lifestyle. " .
                                  "Download it below and let's start! 💪",
                'message_type' => 'pdf',
                'file_url'     => $fileUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Diet plan generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
