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

class GenerateFitnessPlan implements ShouldQueue
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
        try {
            $user  = $this->user->load('goals');
            $goals = $user->goals->pluck('name')->join(', ');

            $prompt = "You are Rakhi, an expert fitness coach.
                Create a 4-week progressive fitness plan for:
                Name: {$user->first_name}
                Age: {$user->age()} years
                Weight: {$user->weight} kg
                Goals: {$goals}
                Activity level: {$user->activity_level}

                Return ONLY valid JSON:
                {
                  'weeks': [
                    {
                      'focus': 'week focus',
                      'days': [
                        {
                          'day': 'Monday',
                          'description': 'workout description',
                          'exercises': ['exercise1', 'exercise2'],
                          'duration': 30
                        }
                      ]
                    }
                  ],
                  'tips': ['tip1', 'tip2']
                }

                Include rest days. Make it realistic for Indians.
                Return ONLY JSON. No extra text.";

            $response = $llm->chat($prompt);
            $clean    = preg_replace('/```json|```/', '', $response);
            $clean    = trim($clean);
            $jsonStart = strpos($clean, '{');
            $jsonEnd   = strrpos($clean, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $clean = substr($clean, $jsonStart, $jsonEnd - $jsonStart + 1);
            }
            $planData = json_decode($clean, true);

            if (!$planData) {
                throw new \Exception('Invalid fitness plan JSON: ' . substr($response, 0, 200));
            }

            $fileUrl = $pdf->generateFitnessPlan($user, $planData);

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'fitness',
                'coach_id'     => 3,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'plan_data'    => $planData,
                'generated_at' => now(),
            ]);

            ChatMessage::create([
                'session_id'   => $this->sessionId,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => "Your personalized Fitness Plan is ready! 💪\n\n" .
                                  "I've created a 4-week progressive plan just for you. " .
                                  "Start slow and be consistent — that's the Rakhi way! 🌸",
                'message_type' => 'pdf',
                'file_url'     => $fileUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Fitness plan failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
