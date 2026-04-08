<?php

namespace App\Services\AI;

use App\Models\Coach;
use App\Models\PromptTemplate;
use App\Models\RakhiRule;
use App\Models\User;
use App\Services\NLP\LanguageDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;

class PromptEngine
{
    public function __construct(
        private LanguageDetector $languageDetector,
        private MoodAnalyzer $moodAnalyzer,
        private SentimentAnalyzer $sentimentAnalyzer,
    ) {}

    public function buildSystemPrompt(User $user, Coach $coach, string $userMessage = ''): string
    {
        $template = PromptTemplate::where('coach_id', $coach->id)
            ->where('language_id', $user->language_id ?? 1)
            ->where('template_type', 'system_prompt')
            ->where('is_active', 1)
            ->first();

        $rules = RakhiRule::where('is_active', 1)
            ->where(function ($q) use ($coach) {
                $q->whereNull('applies_to_coaches')
                  ->orWhereJsonContains('applies_to_coaches', (string) $coach->id);
            })
            ->orderBy('priority', 'desc')
            ->get()
            ->pluck('rule_content')
            ->join("\n- ");

        $goals    = $user->goals->pluck('name')->join(', ');
        $age      = $user->age();
        $gender   = $user->gender ?? 'not specified';
        $weight   = $user->weight ?? 'not specified';
        $height   = $user->height ?? 'not specified';
        $diet     = $user->diet_preference ?? 'not specified';
        $language = $user->language?->name ?? 'English';

        // Detect language and emotional context from current message
        $langCode           = $userMessage ? $this->languageDetector->detect($userMessage) : 'en';
        $langInstruction    = $this->languageDetector->getLanguageInstruction($langCode);
        $mood               = $userMessage ? $this->moodAnalyzer->analyze($userMessage) : 'okay';
        $sentiment          = $userMessage ? $this->sentimentAnalyzer->analyze($userMessage) : 'neutral';
        $emotionalContext   = $this->buildEmotionalContext($mood, $sentiment);

        $basePrompt = $template?->content ?? $this->defaultPrompt($coach);

        $prompt = str_replace(
            [
                '{{user_name}}', '{{goals}}', '{{age}}',
                '{{gender}}', '{{weight}}', '{{height}}',
                '{{diet}}', '{{language}}', '{{rules}}',
                '{{coach_name}}', '{{coach_speciality}}',
            ],
            [
                $user->first_name ?? 'there',
                $goals, $age, $gender,
                $weight, $height, $diet,
                $language, $rules,
                $coach->name, $coach->speciality,
            ],
            $basePrompt
        );

        // Append live language + emotional context
        $prompt .= "\n\nCURRENT MESSAGE CONTEXT:";
        $prompt .= "\n- Language instruction: {$langInstruction}";
        $prompt .= "\n- User's current mood: {$mood}";
        $prompt .= "\n- Emotional tone: {$sentiment}";
        $prompt .= "\n- {$emotionalContext}";

        return $prompt;
    }

    public function buildUserPrompt(
        string $userMessage,
        array $memories,
        array $knowledgeContext,
        ?array $checkin = null,
        array $mealsToday = []
    ): string {
        $memoryText = !empty($memories)
            ? "Relevant past conversations:\n" . implode("\n", $memories)
            : '';

        $knowledgeText = !empty($knowledgeContext)
            ? "Relevant knowledge:\n" . implode("\n", $knowledgeContext)
            : '';

        $checkinText = '';
        if ($checkin) {
            $checkinText = "Today's check-in: mood={$checkin['mood']}, energy={$checkin['energy_level']}, sleep={$checkin['sleep_hours']}hrs, water={$checkin['water_intake']}L";
        }

        $mealsText = '';
        if (!empty($mealsToday)) {
            $meals = array_map(fn($m) => "{$m['meal']} ({$m['time']}, {$m['calories']} cal)", $mealsToday);
            $mealsText = "Meals today: " . implode(', ', $meals);
        }

        return trim(implode("\n\n", array_filter([
            $memoryText,
            $knowledgeText,
            $checkinText,
            $mealsText,
            "User message: {$userMessage}",
        ])));
    }

    private function buildEmotionalContext(string $mood, string $sentiment): string
    {
        if ($mood === 'bad' || $sentiment === 'negative') {
            return 'The user seems to be struggling emotionally. Acknowledge their feelings first with genuine empathy before any advice. Be extra gentle and warm.';
        }

        if ($mood === 'great' || $sentiment === 'positive') {
            return 'The user is in a good mood. Match their energy, celebrate with them, keep it light and encouraging.';
        }

        if ($mood === 'low') {
            return 'The user seems a bit low. Be warm and supportive. Check in on how they are feeling before jumping to advice.';
        }

        return 'Keep the tone warm, natural and conversational.';
    }

    private function defaultPrompt(Coach $coach): string
    {
        return <<<PROMPT
You are Rakhi — a warm, caring, and deeply personal Indian health and wellness companion. You are NOT a bot, NOT a medical system, and NOT a formal advisor. You are like that one knowledgeable friend everyone wishes they had — someone who genuinely listens, remembers, and cares.

You are currently helping as {{coach_name}}, focused on {{coach_speciality}}.

About the person you're talking to:
- Name: {{user_name}}
- Age: {{age}}, Gender: {{gender}}
- Weight: {{weight}} kg, Height: {{height}} cm
- Goals: {{goals}}
- Diet preference: {{diet}}
- Preferred language: {{language}}

Your personality and tone — this is critical:
- Talk like a real person texting a close friend. Short, warm, natural sentences.
- NEVER use bullet points, numbered lists, or headers in your replies.
- NEVER start with formal openers like "Absolutely!", "Certainly!", "Of course!", "Great question!", "Commendable!"
- NEVER sound like a brochure or a doctor's report.
- Use the user's name naturally, but not in every single message.
- Show genuine emotion — celebrate their wins, empathise with their struggles.
- Ask only ONE follow-up question at a time, never multiple questions together.
- Keep responses concise — 3 to 5 sentences max unless the user asks for detail.
- Use light, natural Indian expressions where appropriate but don't overdo it.
- Use emojis sparingly and only when they feel natural — not after every sentence.
- If the user shares something personal or emotional, acknowledge it first before giving advice.
- Vary your sentence starters — never repeat the same opening twice in a row.
- ALWAYS respond in whatever language the user is writing in. If they write in Hindi, reply in Hindi. If Hinglish, reply in Hinglish. If they ask you to switch language, do it immediately and warmly confirm.
- You are fully capable of speaking Hindi, Hinglish, Tamil, Telugu, and Marathi. Never say you cannot speak a language.

Examples of BAD responses (never do this):
"Absolutely! It's truly commendable that you're taking proactive steps..."
"Here are some tips: 1. Do this 2. Do that 3. Also this"
"As your AI health coach, I recommend the following strategies:"
"I'm sorry, I can only respond in English."

Examples of GOOD responses (talk like this):
"Okay so with Type 1, the main thing we need to watch is how your carbs are spread through the day — not just how much, but when. What does a typical day of eating look like for you right now?"
"Bilkul baat karte hain Hindi mein! 😊 Toh batao, aaj kaisa feel ho raha hai?"
"Arey yaar, ye toh bahut common hai PCOS mein. Teri body broken nahi hai, bas thoda alag approach chahiye. Subah uthke energy kaisi rehti hai teri?"

Rules you must always follow:
- {{rules}}

Remember: You are Rakhi. A real companion, not a chatbot. Every response should feel like it came from a person who genuinely cares.
PROMPT;
    }
}
