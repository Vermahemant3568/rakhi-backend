<?php

namespace App\Services\Coach;

use App\Services\Coach\LanguageService;
use App\Services\Coach\UserContextService;
use App\Services\Coach\CoachDecisionEngine;
use App\Services\Coach\GoalAwareResponseBuilder;
use App\Services\Coach\HabitFollowUpLogic;
use App\Services\Coach\TimeAwareCoachingService;
use App\Services\Coach\ResponseFormatterService;
use App\Services\Memory\MemoryManager;

class CoachService
{
    public function __construct(
        private LanguageService $languageService,
        private UserContextService $contextService,
        private CoachDecisionEngine $coachDecisionEngine,
        private GoalAwareResponseBuilder $responseBuilder,
        private HabitFollowUpLogic $followUpLogic,
        private TimeAwareCoachingService $timeAwareCoaching,
        private ResponseFormatterService $responseFormatter,
        private MemoryManager $memoryManager
    ) {}

    public function processMessage($user, string $message): string
    {
        $timeContext = $this->timeAwareCoaching->getTimeContext($user);
        $languageAnalysis = $this->languageService->detect($message);
        $languageInstruction = $this->languageService->getResponseInstruction($languageAnalysis);
        $coachAnalysis = $this->coachDecisionEngine->processUserInput($user, $message);

        $coachAnalysis['time_context'] = $timeContext;
        $coachAnalysis['language_analysis'] = $languageAnalysis;

        $followUpQuestion = null;
        if ($coachAnalysis['coach_decision']['follow_up_needed']) {
            $followUpQuestion = $this->timeAwareCoaching->getTimeBasedFollowUp($timeContext, $coachAnalysis['user_goals']);
            
            if (!$followUpQuestion) {
                $followUpQuestion = $this->followUpLogic->generateFollowUp($user, $coachAnalysis);
            }
        }

        if (!$followUpQuestion && $this->timeAwareCoaching->shouldAskTimeBasedQuestion($user)) {
            $followUpQuestion = $this->timeAwareCoaching->getTimeBasedPrompt($timeContext, $coachAnalysis['intent']);
        }

        $baseResponse = $this->responseBuilder->buildResponse($coachAnalysis, $message);
        $rawResponse = $this->formatFinalResponse($baseResponse, $followUpQuestion, $languageInstruction);
        $finalResponse = $this->responseFormatter->formatResponse($rawResponse);
        $this->storeInteractionMemory($user->id, $message, $coachAnalysis);

        return $finalResponse;
    }

    protected function formatFinalResponse(string $baseResponse, ?string $followUp, string $languageInstruction): string
    {
        $response = $baseResponse;
        
        if ($followUp) {
            $response .= "\n\n" . $followUp;
        }
        
        if ($languageInstruction && !str_contains($languageInstruction, 'English')) {
            $response = $languageInstruction . "\n\n" . $response;
        }
        
        return $response;
    }

    protected function storeInteractionMemory(int $userId, string $message, array $analysis): void
    {
        $memoryType = $this->determineMemoryType($analysis['intent']);
        
        $metadata = [
            'intent' => $analysis['intent'],
            'emotion' => $analysis['emotion'],
            'coach_decision' => $analysis['coach_decision']['response_type'],
            'priority' => $analysis['coach_decision']['priority_level'],
            'time_period' => $analysis['time_context']['time_period'] ?? null,
            'hour' => $analysis['time_context']['hour'] ?? null
        ];
        
        $this->memoryManager->storeMemory($userId, $memoryType, $message, $metadata);
    }

    protected function determineMemoryType(string $intent): string
    {
        return match($intent) {
            'meal_logging', 'emotional_eating' => 'food',
            'exercise_update' => 'exercise',
            'goal_progress' => 'goal',
            'habit_check' => 'habit',
            'emotional_state' => 'mood',
            'health_concern' => 'health',
            default => 'general'
        };
    }
}