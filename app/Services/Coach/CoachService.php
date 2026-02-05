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
    protected $languageService;
    protected $contextService;
    protected $coachDecisionEngine;
    protected $responseBuilder;
    protected $followUpLogic;
    protected $timeAwareCoaching;
    protected $responseFormatter;
    protected $memoryManager;

    public function __construct()
    {
        $this->languageService = new LanguageService();
        $this->contextService = new UserContextService();
        $this->coachDecisionEngine = new CoachDecisionEngine();
        $this->responseBuilder = new GoalAwareResponseBuilder();
        $this->followUpLogic = new HabitFollowUpLogic();
        $this->timeAwareCoaching = new TimeAwareCoachingService();
        $this->responseFormatter = new ResponseFormatterService();
        $this->memoryManager = new MemoryManager();
    }

    public function processMessage($user, string $message): string
    {
        // 1. Get time context for user
        $timeContext = $this->timeAwareCoaching->getTimeContext($user);

        // 2. Advanced language detection and analysis
        $languageAnalysis = $this->languageService->detect($message);
        $languageInstruction = $this->languageService->getResponseInstruction($languageAnalysis);

        // 3. Coach reasoning engine - the core intelligence
        $coachAnalysis = $this->coachDecisionEngine->processUserInput($user, $message);

        // 4. Add time and language context to analysis
        $coachAnalysis['time_context'] = $timeContext;
        $coachAnalysis['language_analysis'] = $languageAnalysis;

        // 5. Generate time-aware follow-up
        $followUpQuestion = null;
        if ($coachAnalysis['coach_decision']['follow_up_needed']) {
            // Try time-based follow-up first
            $followUpQuestion = $this->timeAwareCoaching->getTimeBasedFollowUp($timeContext, $coachAnalysis['user_goals']);
            
            // Fallback to regular follow-up
            if (!$followUpQuestion) {
                $followUpQuestion = $this->followUpLogic->generateFollowUp($user, $coachAnalysis);
            }
        }

        // 6. Check if we should ask time-based questions
        if (!$followUpQuestion && $this->timeAwareCoaching->shouldAskTimeBasedQuestion($user)) {
            $followUpQuestion = $this->timeAwareCoaching->getTimeBasedPrompt($timeContext, $coachAnalysis['intent']);
        }

        // 7. Build goal-aware response with time and language context
        $baseResponse = $this->responseBuilder->buildResponse($coachAnalysis, $message);

        // 8. Add language instruction and follow-up
        $rawResponse = $this->formatFinalResponse($baseResponse, $followUpQuestion, $languageInstruction);

        // 9. Format response for user-friendliness
        $finalResponse = $this->responseFormatter->formatResponse($rawResponse);

        // 10. Store interaction as memory
        $this->storeInteractionMemory($user->id, $message, $coachAnalysis);

        return $finalResponse;
    }

    protected function formatFinalResponse(string $baseResponse, ?string $followUp, string $languageInstruction): string
    {
        $response = $baseResponse;
        
        if ($followUp) {
            $response .= "\n\n" . $followUp;
        }
        
        // Apply language instruction if needed
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
        $intentToMemoryMap = [
            'meal_logging' => 'food',
            'exercise_update' => 'exercise',
            'goal_progress' => 'goal',
            'habit_check' => 'habit',
            'emotional_state' => 'mood',
            'health_concern' => 'health',
            'emotional_eating' => 'food'
        ];
        
        return $intentToMemoryMap[$intent] ?? 'general';
    }
}