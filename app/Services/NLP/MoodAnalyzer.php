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
            'frustrated' => [
                'frustrated', 'frustrat', 'irritated', 'irritat', 'fed up', 'fed-up',
                'kuch kaam nahi kar raha', 'koi fayda nahi', 'bekaar', 'useless',
                'nothing works', 'not working', 'giving up', 'chod deta', 'chod deti',
                'kya fayda', 'koi result nahi', 'no result', 'no progress',
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

    /**
     * Detect energy level signal from message.
     * Returns: 'high' | 'low' | 'normal'
     */
    public function detectEnergyLevel(string $message): string
    {
        $lower = strtolower($message);

        $lowEnergy = [
            'no energy', 'low energy', 'tired', 'exhausted', 'thaka', 'thakaan',
            'kamzori', 'weak', 'drained', 'sluggish', 'lethargy', 'fatigue',
            'bahut thak', 'uthne ka mann nahi', 'kuch karne ka mann nahi',
        ];
        $highEnergy = [
            'energetic', 'full energy', 'active', 'feeling great', 'motivated',
            'fresh', 'charged', 'ready', 'josh', 'taiyaar', 'ekdum fit',
        ];

        foreach ($lowEnergy as $kw) {
            if (str_contains($lower, $kw)) return 'low';
        }
        foreach ($highEnergy as $kw) {
            if (str_contains($lower, $kw)) return 'high';
        }

        return 'normal';
    }

    /**
     * Detect adherence signal from message.
     * Returns: 'consistent' | 'struggling' | 'improving' | 'irregular' | 'unknown'
     */
    public function detectAdherence(string $message): string
    {
        $lower = strtolower($message);

        $consistent = [
            'following', 'sticking to', 'on track', 'consistent', 'every day',
            'roz kar raha', 'roz kar rahi', 'nahi choda', 'continue kar raha',
        ];
        $struggling = [
            'not following', 'skipped', 'missed', 'forgot', 'cheat', 'broke diet',
            'nahi kar paya', 'nahi kar payi', 'bhool gaya', 'bhool gayi',
            'chhod diya', 'nahi hua', 'fail', 'slip', 'relapse',
        ];
        $improving = [
            'getting better', 'improving', 'better than before', 'progress',
            'pehle se better', 'sudhar raha', 'sudhar rahi', 'thoda better',
        ];
        $irregular = [
            'sometimes', 'kabhi kabhi', 'not always', 'irregular', 'on and off',
            'kuch din', 'kuch baar', 'mostly not', 'zyada nahi',
        ];

        foreach ($consistent  as $kw) { if (str_contains($lower, $kw)) return 'consistent'; }
        foreach ($improving   as $kw) { if (str_contains($lower, $kw)) return 'improving'; }
        foreach ($struggling  as $kw) { if (str_contains($lower, $kw)) return 'struggling'; }
        foreach ($irregular   as $kw) { if (str_contains($lower, $kw)) return 'irregular'; }

        return 'unknown';
    }
}
