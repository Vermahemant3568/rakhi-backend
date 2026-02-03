<?php

namespace App\Services\NLP;

use App\Services\AI\AiService;

class EmotionService
{
    public function detect(string $text): string
    {
        try {
            $prompt = "Analyze the emotion in this text and respond with only one word: happy, sad, angry, excited, anxious, neutral, or low_energy.\n\nText: {$text}";
            
            $response = (new AiService())->reply($prompt);
            $emotion = strtolower(trim($response));
            
            // Validate response
            $validEmotions = ['happy', 'sad', 'angry', 'excited', 'anxious', 'neutral', 'low_energy'];
            return in_array($emotion, $validEmotions) ? $emotion : $this->fallbackDetection($text);
            
        } catch (\Exception $e) {
            return $this->fallbackDetection($text);
        }
    }
    
    private function fallbackDetection(string $text): string
    {
        $text = strtolower($text);
        
        if (str_contains($text, 'sad') || str_contains($text, 'depressed')) {
            return 'sad';
        }
        if (str_contains($text, 'happy') || str_contains($text, 'excited')) {
            return 'happy';
        }
        if (str_contains($text, 'tired') || str_contains($text, 'exhausted')) {
            return 'low_energy';
        }
        
        return 'neutral';
    }
}