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
                'feeling great', 'so good', 'best day', 'loving it',
            ],
            'happy' => [
                'good', 'happy', 'acha', 'accha', 'khush', 'better', 'well',
                'energetic', 'motivated', 'positive', 'maja aa raha', 'achha lag raha',
                'feeling good', 'doing well', 'much better', 'improving',
            ],
            'sad' => [
                'sad', 'upset', 'dukhi', 'udaas', 'bura lag', 'heartbroken',
                'crying', 'rona aa raha', 'dil bhar aaya', 'feel like crying',
                'not okay', 'feeling down', 'low mood', 'demotivated', 'hopeless',
                'haar gaya', 'haar gayi', 'himmat nahi', 'kuch nahi ho raha',
            ],
            'stressed' => [
                'stressed', 'stress', 'tension', 'anxious', 'anxiety', 'worried',
                'overwhelmed', 'pressure', 'panic', 'ghabra', 'pareshan',
                'bahut tension', 'kaam ka pressure', 'office stress', 'burnout',
                'cant handle', 'too much', 'breaking point', 'mind full',
            ],
            'tired' => [
                'tired', 'exhausted', 'thaka', 'thak gaya', 'thak gayi', 'fatigue',
                'no energy', 'low energy', 'drained', 'sleepy', 'neend aa rahi',
                'bahut thak', 'body ache', 'weak', 'kamzori', 'lethargy',
                'cant get up', 'always tired', 'thakaan',
            ],
            'okay' => [
                'okay', 'fine', 'normal', 'theek', 'alright', 'so so',
                'chalega', 'chal raha', 'bas theek', 'not bad', 'managing',
                'average', 'same as usual', 'nothing special',
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
