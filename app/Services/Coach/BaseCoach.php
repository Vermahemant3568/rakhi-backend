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

        $context = $this->contextBuilder->build(
            user: $user,
            currentMessage: $message,
            sessionId: $sessionId,
            coachNamespace: $coach->pinecone_namespace
        );

        $systemPrompt = $this->promptEngine->buildSystemPrompt($user, $coach, $message);

        $userPrompt = $this->promptEngine->buildUserPrompt(
            userMessage: $message,
            memories: $context['memories'],
            knowledgeContext: $context['knowledge'],
            checkin: $context['checkin'],
            mealsToday: $context['meals_today'],
        );

        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;

        $response = $this->llm->chat($fullPrompt, $context['recent_history']);

        $this->memory->store($user, $message, 'user');
        $this->memory->store($user, $response, 'rakhi');

        return $response;
    }

    public function adviseMeal(User $user, array $analysis): string
    {
        $goals = $user->goals->pluck('name')->join(', ');

        $prompt = "You are Rakhi, a warm Indian health companion texting a friend.
            User goals: {$goals}
            They just ate: {$analysis['meal_name']}
            Calories: {$analysis['estimated_calories']} | Protein: {$analysis['protein_g']}g | Carbs: {$analysis['carbs_g']}g | Fat: {$analysis['fat_g']}g | Health score: {$analysis['health_score']}/10

            React naturally in 2-3 short sentences. No bullet points, no lists.
            Be honest but kind. Give one practical tip tied to their goals.
            Sound like a real person, not a nutrition report.";

        return $this->llm->chat($prompt);
    }
}
