<?php

namespace App\Services\NLP;

class IntentService
{
    public function detect(string $text): string
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