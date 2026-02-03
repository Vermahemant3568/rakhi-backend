<?php

namespace App\Services\NLP;

use App\Services\AI\AiService;

class IntentService
{
    public function detect(string $text): string
    {
        try {
            $prompt = "Classify the intent of this text. Respond with only one word: diet, fitness, emotional_support, chronic_condition, or general.\n\nText: {$text}";
            
            $response = (new AiService())->reply($prompt);
            $intent = strtolower(trim($response));
            
            // Validate response
            $validIntents = ['diet', 'fitness', 'emotional_support', 'chronic_condition', 'general'];
            return in_array($intent, $validIntents) ? $intent : $this->fallbackDetection($text);
            
        } catch (\Exception $e) {
            return $this->fallbackDetection($text);
        }
    }
    
    private function fallbackDetection(string $text): string
    {
        $text = strtolower($text);
        
        if (str_contains($text, 'diet') || str_contains($text, 'food')) {
            return 'diet';
        }
        if (str_contains($text, 'exercise') || str_contains($text, 'workout')) {
            return 'fitness';
        }
        if (str_contains($text, 'sad') || str_contains($text, 'stress')) {
            return 'emotional_support';
        }
        if (str_contains($text, 'diabetes') || str_contains($text, 'sugar')) {
            return 'chronic_condition';
        }
        
        return 'general';
    }
}