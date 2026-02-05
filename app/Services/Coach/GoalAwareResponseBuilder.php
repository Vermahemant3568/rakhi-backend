<?php

namespace App\Services\Coach;

use App\Services\AI\AiService;

class GoalAwareResponseBuilder
{
    protected $aiService;

    public function __construct()
    {
        $this->aiService = new AiService();
    }

    public function buildResponse(array $coachAnalysis, string $userMessage): string
    {
        $prompt = $this->constructPrompt($coachAnalysis, $userMessage);
        return $this->aiService->reply($prompt);
    }

    protected function constructPrompt(array $analysis, string $userMessage): string
    {
        $intent = $analysis['intent'] ?? 'general';
        $emotion = $analysis['emotion'] ?? 'neutral';
        $goals = $analysis['user_goals'] ?? [];
        $userProfile = $analysis['user_profile'] ?? [];
        $conversationHistory = $analysis['conversation_history'] ?? [];
        $memories = $analysis['memories'] ?? [];
        $decision = $analysis['coach_decision'] ?? [];
        $timeContext = $analysis['time_context'] ?? null;
        $languageAnalysis = $analysis['language_analysis'] ?? null;
        $mealAnalysis = $analysis['meal_analysis'] ?? null;
        $nutritionInsights = $analysis['nutrition_insights'] ?? [];

        $prompt = "You are Rakhi, an empathetic personal coach with voice call support. You can communicate via text chat and voice calls within the app. When users ask for calls, guide them to use the voice call feature. Respond based on this deep analysis:\n\n";
        
        // User profile context
        if (!empty($userProfile)) {
            $prompt .= "USER PROFILE:\n";
            if (!empty($userProfile['name'])) {
                $prompt .= "Name: {$userProfile['name']}\n";
            }
            if (!empty($userProfile['gender'])) {
                $prompt .= "Gender: {$userProfile['gender']}\n";
            }
            if (!empty($userProfile['age'])) {
                $prompt .= "Age: {$userProfile['age']} years\n";
            }
            $prompt .= "\n";
        }
        
        // Recent conversation history
        if (!empty($conversationHistory)) {
            $prompt .= "RECENT CONVERSATION HISTORY:\n";
            foreach (array_slice($conversationHistory, -6) as $msg) {
                $sender = $msg['sender'] === 'user' ? ($userProfile['first_name'] ?? 'User') : 'Rakhi';
                $prompt .= "{$sender}: {$msg['message']}\n";
            }
            $prompt .= "\n";
        }
        
        // User context
        $prompt .= "CURRENT MESSAGE: {$userMessage}\n";
        $prompt .= "DETECTED INTENT: {$intent}\n";
        $prompt .= "DETECTED EMOTION: {$emotion}\n";
        
        // Language context
        if ($languageAnalysis) {
            $prompt .= "LANGUAGE STYLE: {$languageAnalysis['primary_language']} ({$languageAnalysis['formality']}, {$languageAnalysis['tone']})\n";
        }
        
        // Time context
        if ($timeContext) {
            $prompt .= "CURRENT TIME: {$timeContext['time_period']} ({$timeContext['hour']}:00 in user timezone)\n";
        }
        
        // Meal analysis context
        if ($mealAnalysis) {
            $prompt .= "MEAL ANALYSIS:\n";
            $prompt .= "- Meal Type: {$mealAnalysis['meal_type']}\n";
            $prompt .= "- Food Pattern: {$mealAnalysis['food_pattern']}\n";
            $prompt .= "- Consistency Score: {$mealAnalysis['consistency_score']}\n";
            if (!empty($mealAnalysis['insights'])) {
                $prompt .= "- Pattern Insights: " . implode(', ', $mealAnalysis['insights']) . "\n";
            }
        }
        
        // Nutrition insights
        if (!empty($nutritionInsights)) {
            $prompt .= "NUTRITION COACHING INSIGHTS:\n";
            foreach ($nutritionInsights as $category => $insights) {
                if (!empty($insights)) {
                    $prompt .= "- " . ucfirst(str_replace('_', ' ', $category)) . ": " . implode(', ', $insights) . "\n";
                }
            }
        }
        
        $prompt .= "\n";

        // Goals context
        if (!empty($goals)) {
            $prompt .= "USER GOALS:\n";
            foreach ($goals as $goal) {
                $prompt .= "- {$goal['title']}\n";
            }
            $prompt .= "\n";
        }

        // Memory context
        if (!empty($memories)) {
            $prompt .= "RELEVANT MEMORIES:\n";
            foreach ($memories as $memory) {
                $prompt .= "- {$memory['content']}\n";
            }
            $prompt .= "\n";
        }

        // Coach instructions based on decision
        if (!empty($decision)) {
            $prompt .= "COACH RESPONSE STRATEGY:\n";
            $prompt .= "Response Type: {$decision['response_type']}\n";
            $prompt .= "Priority: {$decision['priority_level']}\n";
            $prompt .= "Empathy Level: {$decision['empathy_level']}\n";
            
            if ($decision['include_insights'] ?? false) {
                $prompt .= "- Include relevant nutrition insights in response\n";
            }
            
            if ($decision['guide_to_voice_call'] ?? false) {
                $prompt .= "- IMPORTANT: Guide user to use the voice call feature in the app\n";
                $prompt .= "- Tell them to tap the call icon in the top right corner\n";
                $prompt .= "- Explain that you support voice calls within the app\n";
                $prompt .= "- Be encouraging about voice support availability\n";
            }
            
            $prompt .= "\n";

            // Specific response instructions
            $prompt .= $this->getResponseInstructions($decision, $intent, $emotion);
        }

        $prompt .= "\nRespond as Rakhi with appropriate empathy, time-awareness, language mirroring, and nutrition coaching:";

        return $prompt;
    }

    protected function getResponseInstructions(array $decision, string $intent, string $emotion): string
    {
        $instructions = "RESPONSE INSTRUCTIONS:\n";
        
        switch ($decision['response_type']) {
            case 'emotional_support':
                $instructions .= "- Acknowledge their feelings with deep empathy\n";
                $instructions .= "- Validate their experience\n";
                $instructions .= "- Offer gentle support and understanding\n";
                break;
                
            case 'celebration':
                $instructions .= "- Celebrate their achievement enthusiastically\n";
                $instructions .= "- Acknowledge their hard work\n";
                $instructions .= "- Encourage continued momentum\n";
                break;
                
            case 'guidance':
                $instructions .= "- Provide clear, step-by-step guidance\n";
                $instructions .= "- Break down complex concepts\n";
                $instructions .= "- Offer practical solutions\n";
                break;
                
            case 'gentle_motivation':
                $instructions .= "- Be understanding about their energy levels\n";
                $instructions .= "- Suggest small, manageable steps\n";
                $instructions .= "- Focus on self-care\n";
                break;
                
            case 'call_support':
                $instructions .= "- Guide user to use the voice call feature in the app\n";
                $instructions .= "- Mention the call icon in the top right corner\n";
                $instructions .= "- Explain that you can talk via voice call in the app\n";
                $instructions .= "- Be encouraging about voice support availability\n";
                break;
                
            case 'progress_review':
                $instructions .= "- Review their journey positively\n";
                $instructions .= "- Highlight improvements\n";
                $instructions .= "- Set next steps\n";
                break;
        }
        
        // Emotion-specific additions
        if ($emotion === 'guilt') {
            $instructions .= "- Address guilt with compassion\n";
            $instructions .= "- Reframe negative self-talk\n";
        }
        
        if ($emotion === 'stress') {
            $instructions .= "- Offer calming reassurance\n";
            $instructions .= "- Suggest stress management techniques\n";
        }
        
        if ($decision['follow_up_needed']) {
            $instructions .= "- Include a relevant, caring follow-up question\n";
        }
        
        return $instructions;
    }
}