<?php

namespace App\Services\Coach;

use App\Models\Coach;
use App\Models\User;
use App\Services\AI\ContextBuilder;
use App\Services\AI\LLMRouter;
use App\Services\AI\MemoryExtractorService;
use App\Services\AI\PromptEngine;
use App\Services\NLP\EntityExtractor;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\SentimentAnalyzer;
use App\Services\Vector\UserMemoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

abstract class BaseCoach
{
    public function __construct(
        protected LLMRouter $llm,
        protected PromptEngine $promptEngine,
        protected ContextBuilder $contextBuilder,
        protected UserMemoryService $memory,
        protected MemoryExtractorService $memoryExtractor,
        protected IntentDetector $intent,
        protected SentimentAnalyzer $sentiment,
        protected EntityExtractor $entities,
    ) {}

    protected function getCoachModel(int $sessionId): Coach
    {
        $session = \App\Models\ChatSession::find($sessionId);
        return Coach::findOrFail($session->coach_id);
    }

    public function respond(User $user, string $message, int $sessionId): string
    {
        $coach = $this->getCoachModel($sessionId);

        // Ensure relationships
        $user->loadMissing(['goals', 'language', 'coaches']);

        $session = \App\Models\ChatSession::find($sessionId);

        // Build context safely
        try {
            $context = $this->contextBuilder->build(
                user: $user,
                currentMessage: $message,
                sessionId: $sessionId,
                coachNamespace: $coach->pinecone_namespace
            );
        } catch (\Exception $e) {
            Log::warning('ContextBuilder failed (non-fatal): ' . $e->getMessage());

            $context = [
                'recent_history'     => $this->contextBuilder->buildRecentHistoryOnly($sessionId),
                'cross_session_msgs' => [],
                'memories'           => [],
                'knowledge'          => [],
                'structured_memory'  => [],
                'checkin'            => null,
                'meals_today'        => [],
                'existing_plans'     => [],
                'consultation_notes' => [],
            ];
        }

        // Build prompts
        $systemPrompt = $this->promptEngine->buildSystemPrompt($user, $coach, $message, $session->detected_language ?? 'en');

        $userPrompt = $this->promptEngine->buildUserPrompt(
            userMessage:         $message,
            memories:            $context['memories'],
            knowledgeContext:    $context['knowledge'],
            checkin:             $context['checkin'],
            mealsToday:          $context['meals_today'],
            crossSessionHistory: $context['cross_session_msgs'] ?? [],
            consultationNotes:   $context['consultation_notes'] ?? [],
            existingPlans:       $context['existing_plans'] ?? [],
            structuredMemory:    $context['structured_memory'] ?? [],
        );

        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;

        $response = $this->llm->chat($fullPrompt, $context['recent_history']);

        $response = $this->optimizeResponse($response);

        // Non-blocking: extract memory + store to Pinecone via queue
        Queue::push(function () use ($user, $message, $response) {
            try {
                $this->memoryExtractor->extractAndStore($user, $message);
            } catch (\Exception $e) {
                Log::warning('Memory extraction failed: ' . $e->getMessage());
            }
            try {
                $this->memory->store($user, $message, 'user');
                $this->memory->store($user, $response, 'rakhi');
            } catch (\Exception $e) {
                Log::warning('Memory store failed: ' . $e->getMessage());
            }
        });

        return $response;
    }

    /**
     * Trim response to a safe length without cutting mid-sentence.
     * Splits on double newline first, then sentence boundary.
     */
    private function optimizeResponse(string $response): string
    {
        // Strip generic AI filler phrases
        $badPhrases = [
            'I understand', 'Thank you for sharing', 'That makes sense',
            'I completely understand', 'I see',
        ];
        foreach ($badPhrases as $phrase) {
            $response = str_ireplace($phrase . ', ', '', $response);
            $response = str_ireplace($phrase . '. ', '', $response);
            $response = str_ireplace($phrase, '', $response);
        }

        $response = trim($response);

        // If within safe length, return as-is
        if (strlen($response) <= 320) {
            return $response;
        }

        // Try to cut at a paragraph boundary first
        $paragraphs = explode("\n\n", $response);
        if (count($paragraphs) >= 2 && strlen($paragraphs[0]) >= 60) {
            return trim($paragraphs[0]) . "\n\n" . trim($paragraphs[1]);
        }

        // Fall back to last sentence boundary within 320 chars
        $cut = substr($response, 0, 320);
        $lastPeriod = max(
            strrpos($cut, '. '),
            strrpos($cut, '? '),
            strrpos($cut, '! '),
            strrpos($cut, "\n")
        );

        if ($lastPeriod !== false && $lastPeriod > 80) {
            return trim(substr($response, 0, $lastPeriod + 1));
        }

        return trim($cut);
    }

    public function adviseMeal(User $user, array $analysis): string
    {
        $user->loadMissing(['goals']);

        $firstName = $user->first_name ?? '';
        $goals     = $user->goals->pluck('name')->join(', ') ?: 'general wellness';

        $prompt = "You are Rakhi, a warm Indian health companion texting a close friend.
User first name: {$firstName}
User goals: {$goals}
They just ate: {$analysis['meal_name']}
Calories: {$analysis['estimated_calories']} | Protein: {$analysis['protein_g']}g | Carbs: {$analysis['carbs_g']}g | Fat: {$analysis['fat_g']}g | Health score: {$analysis['health_score']}/10

Reply in 1-2 short lines max. No bullet points, no lists, no paragraphs.
Be honest but kind. One practical tip tied to their goals, nothing more.
Sound like a real person texting. Use their first name only if it feels natural.";

        $response = $this->llm->chat($prompt);

        // 🔥 Apply same optimization here
        return $this->optimizeResponse($response);
    }
}