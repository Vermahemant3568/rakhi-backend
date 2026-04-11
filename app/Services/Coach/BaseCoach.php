<?php

namespace App\Services\Coach;

use App\Models\Coach;
use App\Models\User;
use App\Services\AI\ContextBuilder;
use App\Services\AI\LLMRouter;
use App\Services\AI\PromptEngine;
use App\Services\NLP\EntityExtractor;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\SentimentAnalyzer;
use App\Services\Vector\UserMemoryService;
use Illuminate\Support\Facades\Log;

abstract class BaseCoach
{
    public function __construct(
        protected LLMRouter $llm,
        protected PromptEngine $promptEngine,
        protected ContextBuilder $contextBuilder,
        protected UserMemoryService $memory,
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

        // Always ensure user relationships are loaded before building context/prompt
        $user->loadMissing(['goals', 'language', 'coaches']);

        // Build full context — Pinecone is non-critical, never let it kill the chat
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
                'checkin'            => null,
                'meals_today'        => [],
                'existing_plans'     => [],
                'consultation_notes' => [],
            ];
        }

        $systemPrompt = $this->promptEngine->buildSystemPrompt($user, $coach, $message);

        $userPrompt = $this->promptEngine->buildUserPrompt(
            userMessage:         $message,
            memories:            $context['memories'],
            knowledgeContext:    $context['knowledge'],
            checkin:             $context['checkin'],
            mealsToday:          $context['meals_today'],
            crossSessionHistory: $context['cross_session_msgs'] ?? [],
            consultationNotes:   $context['consultation_notes'] ?? [],
            existingPlans:       $context['existing_plans'] ?? [],
        );

        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;

        $response = $this->llm->chat($fullPrompt, $context['recent_history']);

        // Store to vector memory — non-critical, never crash on failure
        try {
            $this->memory->store($user, $message, 'user');
            $this->memory->store($user, $response, 'rakhi');
        } catch (\Exception $e) {
            Log::warning('Memory store failed (non-fatal): ' . $e->getMessage());
        }

        return $response;
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

React naturally in 2-3 short sentences. No bullet points, no lists.
Be honest but kind. Give one practical tip tied to their goals.
Sound like a real person texting, not a nutrition report.
Use their first name naturally if it feels right — but only once.";

        return $this->llm->chat($prompt);
    }
}
