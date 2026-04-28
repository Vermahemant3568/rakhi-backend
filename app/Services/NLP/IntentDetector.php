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
        'self_intro' => [
            'who are you', 'what are you', 'how do you work', 'how does this work',
            'are you an ai', 'are you a bot', 'are you real', 'are you human',
            'what is rakhi', 'tell me about yourself', 'introduce yourself',
            'aap kaun ho', 'aap kya ho', 'aap kaise kaam karti ho', 'rakhi kaun hai',
            'kya aap ai ho', 'kya aap robot ho', 'kya aap real ho', 'aap real ho',
            'aapke baare mein', 'apne baare mein batao', 'tumhare baare mein',
            'how were you trained', 'what model are you', 'which ai', 'what technology',
            'kaise seekha', 'kahan se seekha', 'aapko kisne banaya',
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

    /**
     * Classify message into: greeting | follow_up | simple | complex
     * Used by ContextBuilder and BaseCoach to decide context depth.
     */
    public function classifyDepth(string $message, ?string $lastRakhiMessage = null): string
    {
        $lower = strtolower(trim($message));

        // Pure greetings / one-word acks
        if (preg_match('/^(hi|hey|hello|ok|okay|thanks|thank you|haan|hmm|yes|no|k|sure|got it|will try|noted|nice|great|good|cool|fine|theek|acha|accha|bilkul|shukriya|👍|😊|🙏)[\s!.]*$/', $lower)) {
            return 'greeting';
        }

        // Short follow-up acknowledgements
        if (strlen($lower) < 25 && preg_match('/^(okay will|sure will|i will|let me try|trying|understood|will do|sounds good|makes sense)/', $lower)) {
            return 'greeting';
        }

        // Follow-up answer: short reply that answers Rakhi's previous question
        if ($lastRakhiMessage && str_contains($lastRakhiMessage, '?') && $this->looksLikeFollowUpAnswer($lower)) {
            return 'follow_up';
        }

        // Health keywords → always complex
        $healthKeywords = [
            'sugar', 'blood', 'weight', 'sleep', 'stress', 'diet', 'eat', 'food',
            'exercise', 'tired', 'pain', 'medicine', 'thyroid', 'pcos', 'diabetes',
            'energy', 'mood', 'anxiety', 'period', 'pregnancy', 'insulin', 'bp',
            'cholesterol', 'vitamin', 'protein', 'calories', 'workout', 'gym',
            'numb', 'tingling', 'swelling', 'burning', 'cramp', 'weakness',
            'fever', 'headache', 'dizzy', 'nausea', 'vomit', 'breathe',
            'dard', 'pero', 'pair', 'haath', 'pet', 'sar', 'seena', 'kamar',
            'khana', 'khaana', 'neend', 'thakan', 'dawai', 'tablet', 'injection',
            'sujan', 'jalan', 'khujli', 'kamzori', 'chakkar', 'bukhaar', 'ulti',
            'sans', 'dil', 'aankhein', 'peeshab', 'peshab', 'pyaas', 'bhookh',
            'motapa', 'vajan', 'periods', 'mahavari', 'garbh', 'sugar level',
        ];

        foreach ($healthKeywords as $kw) {
            if (str_contains($lower, $kw)) return 'complex';
        }

        return strlen($lower) < 40 ? 'simple' : 'complex';
    }

    private function looksLikeFollowUpAnswer(string $lower): bool
    {
        $patterns = [
            '/\b(se|since|from|ago|pehle|pahle)\b/',
            '/\b(saam|subah|raat|dopahar|morning|evening|night|afternoon|abhi|kal|aaj|parso)\b/',
            '/\b(\d+|ek|do|teen|char|paanch)\s*(din|ghante|hafte|mahine|week|month|hour|day|minute)\b/',
            '/^(haan|nahi|nai|ha|na)\s+\w+/',
            '/^(bahut|thoda|zyada|kam|bilkul|kabhi kabhi|aksar|hamesha|rarely|sometimes|always|never)[\s!.]*$/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $lower)) return true;
        }
        return false;
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

    public function isSelfIntro(string $message): bool
    {
        return $this->detect($message) === 'self_intro';
    }
}
