<?php

namespace App\Services\Coach;

class MealIntentAnalyzer
{
    public function analyze(string $message): array
    {
        $message = strtolower($message);
        
        // 🧠 1️⃣ Intent → Meal Context Detection
        if ($this->isGuiltPattern($message)) {
            return [
                'intent' => 'guilt',
                'emotion' => 'negative',
                'response_type' => 'supportive',
                'context' => 'emotional_eating'
            ];
        }
        
        if ($this->isMealLogging($message)) {
            return [
                'intent' => 'meal_logging',
                'emotion' => 'neutral',
                'response_type' => 'analytical',
                'context' => 'tracking'
            ];
        }
        
        if ($this->isFeedbackRequest($message)) {
            return [
                'intent' => 'feedback',
                'emotion' => 'curious',
                'response_type' => 'educational',
                'context' => 'improvement'
            ];
        }
        
        return [
            'intent' => 'general',
            'emotion' => 'neutral',
            'response_type' => 'conversational',
            'context' => 'chat'
        ];
    }
    
    private function isGuiltPattern(string $message): bool
    {
        $guiltKeywords = [
            'galat kha liya', 'zyada kha liya', 'cheat kiya',
            'guilt', 'regret', 'mistake', 'wrong food',
            'bad eating', 'overate'
        ];
        
        foreach ($guiltKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isMealLogging(string $message): bool
    {
        $loggingKeywords = [
            'khaya', 'ate', 'breakfast', 'lunch', 'dinner',
            'meal', 'food', 'kha liya'
        ];
        
        foreach ($loggingKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isFeedbackRequest(string $message): bool
    {
        $feedbackKeywords = [
            'kaisa hai', 'how is', 'feedback', 'opinion',
            'theek hai', 'good or bad', 'healthy'
        ];
        
        foreach ($feedbackKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
}