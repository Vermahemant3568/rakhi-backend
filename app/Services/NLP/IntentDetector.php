<?php

namespace App\Services\NLP;

class IntentDetector
{
    private array $intents = [
        'language_switch' => [
            'hindi mein', 'hindi me', 'in hindi', 'hindi bolo', 'hinglish',
            'in english', 'english mein', 'tamil mein', 'telugu lo', 'speak hindi',
        ],
        'meal_log' => [
            'i ate', 'i had', 'i eat', 'my meal', 'breakfast', 'lunch', 'dinner', 'snack',
            'khaya', 'khaana', 'khana khaya', 'subah khaya', 'raat ko khaya', 'maine khaya',
        ],
        'mood_update' => [
            'feeling', 'feel', 'mood', 'sad', 'happy', 'anxious', 'stressed', 'tired',
            'thak', 'pareshan', 'udaas', 'khush', 'tension', 'ghabra', 'dara',
        ],
        'diet_question' => [
            'what should i eat', 'diet', 'food', 'recipe', 'calories', 'nutrition',
            'khana', 'kya khaaun', 'kya khana chahiye', 'diet plan', 'khaana batao',
        ],
        'fitness' => [
            'workout', 'exercise', 'gym', 'walk', 'run', 'yoga',
            'vyayam', 'kasrat', 'exercise batao', 'workout karna',
        ],
        'sleep' => [
            'sleep', 'insomnia', 'tired', 'rest', 'neend', 'so nahi', 'neend nahi',
            'raat ko neend', 'sone mein', 'jagta rehta',
        ],
        'pain' => [
            'pain', 'ache', 'hurt', 'dard', 'taklif', 'dard ho raha', 'dukh raha',
        ],
        'emergency' => [
            'chest pain', 'can\'t breathe', 'unconscious', 'bleeding', 'heart attack',
            'sans nahi', 'behosh', 'khoon', 'dil mein dard',
        ],
        'progress' => [
            'progress', 'how am i doing', 'weight', 'result', 'streak',
            'kitna weight', 'kya progress', 'result kya',
        ],
        'greeting' => [
            'hi', 'hello', 'hey', 'namaste', 'namaskar', 'good morning', 'good evening',
            'good night', 'kaise ho', 'kaisi ho', 'sab theek', 'kya haal',
        ],
        'plan_request' => [
            'give me a plan', 'diet plan', 'fitness plan', 'create plan', 'plan banao',
            'mera plan', 'plan chahiye', 'plan do',
        ],
        'motivation' => [
            'motivate', 'inspire', 'give up', 'cant do', 'hopeless', 'no point',
            'haar gaya', 'haar gayi', 'himmat nahi', 'chod deta', 'chod deti',
        ],
        'compliment' => [
            'thank you', 'thanks', 'shukriya', 'bahut acha', 'you are great',
            'helpful', 'maza aaya', 'accha laga',
        ],
    ];

    public function detect(string $message): string
    {
        $lower = strtolower($message);

        foreach ($this->intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    public function isAskingForPlan(string $message): bool
    {
        return $this->detect($message) === 'plan_request';
    }

    public function isMealLog(string $message): bool
    {
        return $this->detect($message) === 'meal_log';
    }

    public function isLanguageSwitch(string $message): bool
    {
        return $this->detect($message) === 'language_switch';
    }
}
