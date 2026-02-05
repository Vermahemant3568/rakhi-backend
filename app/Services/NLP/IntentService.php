<?php

namespace App\Services\NLP;

class IntentService
{
    public function analyze(string $text): string
    {
        $text = strtolower($text);
        
        // Meal logging intent
        if (preg_match('/\b(ate|had|eaten|consumed|finished|meal|breakfast|lunch|dinner|snack|calories|portions?)\b/', $text)) {
            return 'meal_logging';
        }
        
        // Emotional eating patterns
        if (preg_match('/\b(stress eat|comfort food|binge|overate|couldn\'t stop|emotional eating|ate because|feeling hungry but)\b/', $text)) {
            return 'emotional_eating';
        }
        
        // Motivation drop signals
        if (preg_match('/\b(give up|quit|can\'t do|too hard|not working|no point|demotivated|lost motivation|want to stop)\b/', $text)) {
            return 'motivation_drop';
        }
        
        // Asking for call/support
        if (preg_match('/\b(call me|talk to|need support|want to talk|feeling alone|need help|can we talk|mujhe call|call kr|call kro|baat karna|voice call)\b/', $text)) {
            return 'asking_for_call';
        }
        
        // Progress check requests
        if (preg_match('/\b(how am i doing|progress|check|review|assessment|where do i stand|am i improving)\b/', $text)) {
            return 'progress_check';
        }
        
        // Exercise/Fitness related
        if (preg_match('/\b(workout|exercise|gym|run|walk|fitness|training|cardio|strength|yoga)\b/', $text)) {
            return 'exercise_update';
        }
        
        // Goal progress
        if (preg_match('/\b(goal|target|achieve|success|plan|planning|objective)\b/', $text)) {
            return 'goal_progress';
        }
        
        // Habit tracking
        if (preg_match('/\b(habit|routine|daily|regular|practice|consistency|track)\b/', $text)) {
            return 'habit_check';
        }
        
        // Emotional states
        if (preg_match('/\b(feel|feeling|mood|emotion|sad|happy|stressed|angry|frustrated|motivated)\b/', $text)) {
            return 'emotional_state';
        }
        
        // Health concerns
        if (preg_match('/\b(health|sick|pain|diabetes|sugar|pressure|medical|symptoms)\b/', $text)) {
            return 'health_concern';
        }
        
        return 'general';
    }
}