<?php

namespace App\Services\NLP;

class EmotionService
{
    public function detect(string $text): string
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