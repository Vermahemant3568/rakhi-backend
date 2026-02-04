<?php

namespace App\Services\Coach;

class LanguageService
{
    public function detect(string $text): string
    {
        if (preg_match('/[अ-ह]/u', $text)) {
            return 'hi'; // Hindi
        }

        if (str_contains($text, 'ap ') || str_contains($text, 'mujhe')) {
            return 'hinglish';
        }

        return 'en';
    }

    public function getResponseInstruction(string $detectedLanguage): string
    {
        switch ($detectedLanguage) {
            case 'hi':
                return 'Reply in pure Hindi using Devanagari script.';
            case 'hinglish':
                return 'Reply in Hinglish (Hindi words written in English) - natural Indian style mixing Hindi and English.';
            case 'en':
            default:
                return 'Reply in English.';
        }
    }
}