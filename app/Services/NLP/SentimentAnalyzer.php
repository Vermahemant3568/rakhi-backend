<?php

namespace App\Services\NLP;

class SentimentAnalyzer
{
    private array $positive = [
        'good', 'great', 'happy', 'excellent', 'amazing', 'wonderful', 'fantastic',
        'better', 'feeling well', 'excited', 'motivated', 'energetic', 'proud',
        'acha', 'accha', 'badhiya', 'khush', 'mast', 'zabardast', 'shandar',
        'theek hoon', 'bilkul theek', 'maja aa raha', 'achha lag raha',
    ];

    private array $negative = [
        'bad', 'sad', 'tired', 'stressed', 'anxious', 'depressed', 'hopeless',
        'frustrated', 'angry', 'upset', 'exhausted', 'overwhelmed', 'lonely',
        'bura', 'thaka', 'pareshan', 'dukhi', 'udaas', 'tension', 'ghabra',
        'thak gaya', 'thak gayi', 'haar gaya', 'haar gayi', 'himmat nahi',
        'bura lag raha', 'achha nahi lag raha', 'dil nahi kar raha',
    ];

    private array $neutral = [
        'okay', 'fine', 'normal', 'alright', 'so so', 'not bad', 'managing',
        'theek', 'thik hai', 'chalega', 'chal raha', 'bas theek',
    ];

    public function analyze(string $message): string
    {
        $lower = strtolower($message);

        $posScore = 0;
        $negScore = 0;

        foreach ($this->positive as $word) {
            if (str_contains($lower, $word)) $posScore++;
        }

        foreach ($this->negative as $word) {
            if (str_contains($lower, $word)) $negScore++;
        }

        if ($posScore > $negScore) return 'positive';
        if ($negScore > $posScore) return 'negative';
        return 'neutral';
    }
}
