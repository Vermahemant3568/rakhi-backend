<?php

namespace App\Services\Safety;

class MedicalSafetyService
{
    protected array $criticalKeywords = [
        'chest pain',
        'breathing problem',
        'faint',
        'collapse',
        'unconscious',
        'severe bleeding',
        'heart attack',
        'stroke',
        'suicidal',
        'kill myself'
    ];

    public function isCritical(string $text): bool
    {
        $text = strtolower($text);

        foreach ($this->criticalKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}