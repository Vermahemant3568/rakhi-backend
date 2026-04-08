<?php

namespace App\Services\NLP;

class EntityExtractor
{
    public function extractWeight(string $message): ?float
    {
        preg_match('/(\d+\.?\d*)\s*(kg|kilo|kilogram)/i', $message, $matches);
        return isset($matches[1]) ? (float) $matches[1] : null;
    }

    public function extractBloodSugar(string $message): ?float
    {
        preg_match('/(\d+\.?\d*)\s*(mg|mg\/dl|mmol)/i', $message, $matches);
        return isset($matches[1]) ? (float) $matches[1] : null;
    }

    public function extractSleepHours(string $message): ?float
    {
        preg_match('/(\d+\.?\d*)\s*(hour|hr|ghante)/i', $message, $matches);
        return isset($matches[1]) ? (float) $matches[1] : null;
    }

    public function extractMealTime(string $message): ?string
    {
        $message = strtolower($message);
        if (str_contains($message, 'breakfast') || str_contains($message, 'subah'))   return 'breakfast';
        if (str_contains($message, 'lunch')     || str_contains($message, 'dopahar')) return 'lunch';
        if (str_contains($message, 'dinner')    || str_contains($message, 'raat'))    return 'dinner';
        if (str_contains($message, 'snack')     || str_contains($message, 'halka'))   return 'snack';
        return null;
    }
}
