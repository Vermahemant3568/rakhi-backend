<?php

namespace App\Services\NLP;

class IntentDetector
{
    private array $intents = [
        // call_request MUST be before greeting so "hi can you call me" hits call first
        'call_request' => [
            'call me', 'give me a call', 'can you call', 'voice call', 'talk to me',
            'speak to me', 'want to talk', 'lets talk', 'let us talk', "let's talk",
            'phone call', 'audio call', 'call karo', 'call karna', 'baat karna hai',
            'baat karo', 'call chahiye', 'voice pe baat', 'call pe baat',
            'mujhe call karo', 'call kar', 'discuss on call', 'talk on call',
            'voice mein baat', 'sunna chahta', 'sunna chahti',
        ],
        'language_switch' => [
            'hindi mein', 'hindi me', 'in hindi', 'hindi bolo', 'hinglish',
            'in english', 'english mein', 'tamil mein', 'telugu lo', 'speak hindi',
        ],
        'plan_request' => [
            'give me a plan', 'diet plan', 'create plan', 'plan banao',
            'mera plan', 'plan chahiye', 'plan do', 'make me a plan', 'generate plan',
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
            'khana', 'kya khaaun', 'kya khana chahiye', 'khaana batao',
        ],
        'fitness' => [
            'workout', 'exercise', 'gym', 'walk', 'run', 'yoga',
            'vyayam', 'kasrat', 'exercise batao', 'workout karna',
        ],
        'sleep' => [
            'sleep', 'insomnia', 'neend', 'so nahi', 'neend nahi',
            'raat ko neend', 'sone mein', 'jagta rehta',
        ],
        'pain' => [
            'pain', 'ache', 'hurt', 'dard', 'taklif', 'dard ho raha', 'dukh raha',
        ],
        'emergency' => [
            'chest pain', "can't breathe", 'unconscious', 'bleeding', 'heart attack',
            'sans nahi', 'behosh', 'khoon', 'dil mein dard',
        ],
        'progress' => [
            'progress', 'how am i doing', 'result', 'streak',
            'kitna weight', 'kya progress', 'result kya',
        ],
        'greeting' => [
            'hi', 'hello', 'hey', 'namaste', 'namaskar', 'good morning', 'good evening',
            'good night', 'kaise ho', 'kaisi ho', 'sab theek', 'kya haal',
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

    public function isRequestingCall(string $message): bool
    {
        return $this->detect($message) === 'call_request';
    }

    public function isMealLog(string $message): bool
    {
        return $this->detect($message) === 'meal_log';
    }

    public function isLanguageSwitch(string $message): bool
    {
        return $this->detect($message) === 'language_switch';
    }

    public function isCallIssue(string $message): bool
    {
        $lower = strtolower($message);
        $keywords = [
            'call button', 'call not working', 'call is not working', "can't call",
            'cannot call', 'call nahi', 'call ho nahi', 'call fail', 'call button not',
            'not working', 'button not working', 'call se nahi', 'call nahi ho raha',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }
}
