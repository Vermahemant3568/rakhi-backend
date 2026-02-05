<?php

namespace App\Services\Coach;

use App\Services\NLP\IntentService;
use App\Services\NLP\EmotionService;
use App\Services\Memory\MemorySelectorService;
use App\Services\Nutrition\MealPatternAnalyzer;
use App\Services\Nutrition\NutritionInsightService;
use App\Models\Goal;

class CoachDecisionEngine
{
    protected $intentService;
    protected $emotionService;
    protected $memorySelectorService;
    protected $mealPatternAnalyzer;
    protected $nutritionInsightService;

    public function __construct()
    {
        $this->intentService = new IntentService();
        $this->emotionService = new EmotionService();
        $this->memorySelectorService = new MemorySelectorService();
        $this->mealPatternAnalyzer = new MealPatternAnalyzer();
        $this->nutritionInsightService = new NutritionInsightService();
    }

    public function processUserInput($user, string $message): array
    {
        try {
            // Step 1: Intent Analysis
            $intent = $this->intentService->analyze($message);
            
            // Step 2: Emotion Detection
            $emotion = $this->emotionService->detect($message);
            
            // Step 3: User Goals Context
            $userGoals = $this->getUserGoals($user->id);
            
            // Step 4: User Profile Context (with error handling)
            $userProfile = $this->getUserProfile($user);
            
            // Step 5: Recent Conversation History (with error handling)
            $conversationHistory = $this->getRecentConversationHistory($user->id);
            
            // Step 6: Memory Recall
            $relevantMemories = $this->memorySelectorService->getRelevantMemories($user->id, $message, 5);
            
            // Step 7: Meal Pattern Analysis (if meal-related)
            $mealAnalysis = null;
            $nutritionInsights = [];
            if ($intent === 'meal_logging') {
                $mealAnalysis = $this->mealPatternAnalyzer->analyzeMealPattern($user->id, $message, $intent);
                $nutritionInsights = $this->nutritionInsightService->generateNutritionInsights($user->id, $message);
                
                // Store meal pattern memory
                $this->mealPatternAnalyzer->storeMealMemory($user->id, $message, $mealAnalysis);
            }
            
            // Step 8: Coach Decision Logic
            $coachDecision = $this->makeCoachDecision($intent, $emotion, $userGoals, $relevantMemories, $message, $mealAnalysis);
            
            return [
                'intent' => $intent,
                'emotion' => $emotion,
                'user_goals' => $userGoals,
                'user_profile' => $userProfile,
                'conversation_history' => $conversationHistory,
                'memories' => $relevantMemories,
                'meal_analysis' => $mealAnalysis,
                'nutrition_insights' => $nutritionInsights,
                'coach_decision' => $coachDecision
            ];
        } catch (\Exception $e) {
            \Log::error('CoachDecisionEngine error: ' . $e->getMessage());
            
            // Return minimal working data
            return [
                'intent' => 'general',
                'emotion' => 'neutral',
                'user_goals' => [],
                'user_profile' => [],
                'conversation_history' => [],
                'memories' => [],
                'meal_analysis' => null,
                'nutrition_insights' => [],
                'coach_decision' => [
                    'response_type' => 'supportive',
                    'follow_up_needed' => false,
                    'goal_check_needed' => false,
                    'habit_reminder' => false,
                    'priority_level' => 'normal',
                    'empathy_level' => 'standard',
                    'include_insights' => false
                ]
            ];
        }
    }

    protected function getUserGoals(int $userId): array
    {
        return Goal::whereHas('users', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->get(['id', 'title', 'description'])->toArray();
    }

    protected function getUserProfile($user): array
    {
        try {
            $profile = $user->profile;
            if (!$profile) {
                return [];
            }
            
            return [
                'name' => $profile->full_name ?? $profile->first_name,
                'first_name' => $profile->first_name,
                'gender' => $profile->gender,
                'age' => $profile->dob ? now()->diffInYears($profile->dob) : null
            ];
        } catch (\Exception $e) {
            \Log::warning('Error getting user profile: ' . $e->getMessage());
            return [];
        }
    }

    protected function getRecentConversationHistory(int $userId, int $limit = 10): array
    {
        try {
            $conversation = \App\Models\Conversation::where('user_id', $userId)
                ->where('status', 'active')
                ->first();
                
            if (!$conversation) {
                return [];
            }
            
            return \App\Models\Message::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get(['sender', 'message', 'created_at'])
                ->reverse()
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            \Log::warning('Error getting conversation history: ' . $e->getMessage());
            return [];
        }
    }

    protected function makeCoachDecision(string $intent, string $emotion, array $goals, array $memories, string $message, ?array $mealAnalysis): array
    {
        $decision = [
            'response_type' => 'supportive',
            'follow_up_needed' => false,
            'goal_check_needed' => false,
            'habit_reminder' => false,
            'priority_level' => 'normal',
            'empathy_level' => 'standard',
            'include_insights' => false
        ];

        // High priority emotions - immediate empathetic response
        if (in_array($emotion, ['guilt', 'stress', 'sad', 'angry'])) {
            $decision['response_type'] = 'emotional_support';
            $decision['priority_level'] = 'high';
            $decision['empathy_level'] = 'high';
        }

        // Celebration emotions
        if ($emotion === 'proud') {
            $decision['response_type'] = 'celebration';
            $decision['follow_up_needed'] = true;
        }

        // Guidance needed
        if (in_array($emotion, ['confused', 'frustrated'])) {
            $decision['response_type'] = 'guidance';
            $decision['follow_up_needed'] = true;
        }

        // Energy-based responses
        if ($emotion === 'tired') {
            $decision['response_type'] = 'gentle_motivation';
            $decision['empathy_level'] = 'high';
        }

        // Intent-specific decisions
        switch ($intent) {
            case 'meal_logging':
                $decision['goal_check_needed'] = true;
                $decision['habit_reminder'] = true;
                $decision['include_insights'] = true;
                
                // Adjust based on meal analysis
                if ($mealAnalysis && $mealAnalysis['consistency_score'] > 0.7) {
                    $decision['response_type'] = 'celebration';
                } elseif ($mealAnalysis && $mealAnalysis['food_pattern'] === 'junk_food') {
                    $decision['response_type'] = 'gentle_guidance';
                }
                break;
                
            case 'emotional_eating':
                $decision['response_type'] = 'emotional_support';
                $decision['priority_level'] = 'high';
                $decision['empathy_level'] = 'high';
                $decision['follow_up_needed'] = true;
                break;
                
            case 'motivation_drop':
                $decision['response_type'] = 'motivational';
                $decision['priority_level'] = 'high';
                $decision['follow_up_needed'] = true;
                break;
                
            case 'asking_for_call':
                $decision['response_type'] = 'call_support';
                $decision['priority_level'] = 'high';
                $decision['empathy_level'] = 'high';
                $decision['guide_to_voice_call'] = true;
                break;
                
            case 'progress_check':
                $decision['response_type'] = 'progress_review';
                $decision['goal_check_needed'] = true;
                $decision['follow_up_needed'] = true;
                $decision['include_insights'] = true;
                break;
        }

        // Check if user needs motivation based on memories
        if ($this->needsMotivation($memories)) {
            $decision['response_type'] = 'motivational';
            $decision['follow_up_needed'] = true;
        }

        return $decision;
    }

    protected function needsMotivation(array $memories): bool
    {
        foreach ($memories as $memory) {
            if (str_contains(strtolower($memory['content']), 'failed') || 
                str_contains(strtolower($memory['content']), 'couldn\'t') ||
                str_contains(strtolower($memory['content']), 'missed')) {
                return true;
            }
        }
        return false;
    }
}