<?php

namespace App\Services\NLP;

class MoodAnalyzer
{
    public function analyze(string $message): string
    {
        $lower = strtolower($message);

        $moods = [
            'great' => [
                'amazing', 'fantastic', 'excellent', 'wonderful', 'zabardast',
                'mast', 'shandar', 'ekdum fit', 'bahut khush', 'on top',
            ],
            'good' => [
                'good', 'happy', 'acha', 'accha', 'khush', 'better', 'well',
                'energetic', 'motivated', 'positive', 'maja aa raha', 'achha lag raha',
            ],
            'okay' => [
                'okay', 'fine', 'normal', 'theek', 'alright', 'so so',
                'chalega', 'chal raha', 'bas theek', 'not bad', 'managing',
            ],
            'low' => [
                'sad', 'upset', 'not good', 'dukhi', 'bura lag', 'udaas',
                'tired', 'thaka', 'thak gaya', 'thak gayi', 'low energy',
                'dil nahi kar raha', 'achha nahi lag raha',
            ],
            'bad' => [
                'terrible', 'awful', 'depressed', 'very bad', 'bahut bura',
                'hopeless', 'haar gaya', 'haar gayi', 'himmat nahi', 'bahut thak',
                'anxious', 'ghabra', 'pareshan', 'overwhelmed', 'bahut tension',
            ],
        ];

        foreach ($moods as $mood => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $mood;
                }
            }
        }

        return 'okay';
    }
}
