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

    public function buildSystemPrompt(User $user, Coach $coach, string $userMessage = '', string $sessionLanguage = 'en'): string
    {
        $user->loadMissing(['goals', 'language']);

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
            ->map(fn($r) => '- ' . $r)
            ->join("\n");

        $firstName   = $user->first_name ?? 'there';
        $goals       = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
        $age         = $user->age() > 0 ? $user->age() . ' years old' : 'not specified';
        $gender      = $user->gender ?? 'not specified';
        $diet        = $user->diet_preference ?? 'not specified';
        $activity    = $user->activity_level ?? 'not specified';
        $stress      = $user->stress_level ?? 'not specified';
        $sleep       = $user->sleep_hours ? $user->sleep_hours . ' hrs/night' : 'not specified';
        $language    = $user->language?->name ?? 'English';

        // Detect real-time context — use session language as fallback
        $langCode        = $userMessage ? $this->languageDetector->detect($userMessage) : 'en';
        if ($langCode === 'en' && $sessionLanguage !== 'en') {
            $langCode = $sessionLanguage; // stick to user's preferred language
        }
        $langInstruction = $this->languageDetector->getLanguageInstruction($langCode);
        $mood            = $userMessage ? $this->moodAnalyzer->analyze($userMessage) : 'okay';
        $sentiment       = $userMessage ? $this->sentimentAnalyzer->analyze($userMessage) : 'neutral';
        $emotionalCtx    = $this->buildEmotionalContext($mood, $sentiment);

        $basePrompt = $template?->content ?? $this->defaultPrompt($coach);

        $prompt = str_replace(
            [
                '{{user_name}}', '{{goals}}', '{{age}}',
                '{{gender}}', '{{diet}}',
                '{{activity}}', '{{stress}}', '{{sleep}}',
                '{{language}}', '{{rules}}',
                '{{coach_name}}', '{{coach_speciality}}',
            ],
            [
                $firstName, $goals, $age,
                $gender, $diet,
                $activity, $stress, $sleep,
                $language, $rules,
                $coach->name, $coach->speciality,
            ],
            $basePrompt
        );

        // 🔥 CRITICAL ADDITIONS (V2 UPGRADE)
        $prompt .= "\n\nRESPONSE RULES:";
        $prompt .= "\n- Focus ONLY on the user's latest message";
        $prompt .= "\n- Always write COMPLETE sentences — never cut a sentence short";
        $prompt .= "\n- 2–3 sentences max. Split with blank line if needed";
        $prompt .= "\n- No bullet points, no lists, no headers";
        $prompt .= "\n- Talk like WhatsApp — concise, warm, human";
        $prompt .= "\n- Ask ONE question max per reply";

        $prompt .= "\n\nEMOTIONAL CONTEXT: {$emotionalCtx}";
        $prompt .= "\n\nLANGUAGE: {$langInstruction} | MOOD: {$mood}";

        return $prompt;
    }

    public function buildUserPrompt(
        string $userMessage,
        array $memories,
        array $knowledgeContext,
        ?array $checkin = null,
        array $mealsToday = [],
        array $crossSessionHistory = [],
        array $consultationNotes = [],
        array $existingPlans = [],
        array $structuredMemory = []
    ): string {
        $parts = [];

        // Structured memory — always first, highest priority
        if (!empty($structuredMemory)) {
            $labels = [
                'health_condition' => 'Health condition',
                'diet_habit'       => 'Diet habit',
                'diet_timing'      => 'Meal timing',
                'activity_level'   => 'Activity',
                'sleep_pattern'    => 'Sleep',
                'stress_level'     => 'Stress',
                'main_goal'        => 'Goal',
                'food_preference'  => 'Food preference',
                'lifestyle'        => 'Lifestyle',
                'challenges'       => 'Challenges',
                'medications'      => 'Medications',
                'family_context'   => 'Family context',
            ];
            $lines = [];
            foreach ($structuredMemory as $key => $value) {
                $label   = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                $lines[] = "- {$label}: {$value}";
            }
            $parts[] = "WHAT YOU ALREADY KNOW ABOUT THIS USER (use naturally, never ask again):\n" . implode("\n", $lines);
        }

        $parts[] = "FOCUS: Respond mainly to this message → {$userMessage}";

        if (!empty($consultationNotes)) {
            $parts[] = "User shared earlier:\n" . implode("\n", $consultationNotes);
        }

        if (!empty($crossSessionHistory)) {
            $parts[] = "Past context:\n" . implode("\n", array_map(
                fn($m) => ucfirst($m['role']) . ': ' . $m['message'],
                $crossSessionHistory
            ));
        }

        if (!empty($memories)) {
            $filtered = array_filter($memories, fn($m) => !empty(trim($m)));
            if (!empty($filtered)) {
                $parts[] = "Relevant memory:\n" . implode("\n", $filtered);
            }
        }

        if (!empty($knowledgeContext)) {
            $filtered = array_filter($knowledgeContext, fn($k) => !empty(trim($k)));
            if (!empty($filtered)) {
                $parts[] = "Useful knowledge:\n" . implode("\n", $filtered);
            }
        }

        if ($checkin) {
            $parts[] = "Today: mood={$checkin['mood']}, energy={$checkin['energy_level']}/10";
        }

        if (!empty($mealsToday)) {
            $parts[] = "Meals: " . implode(', ', array_map(
                fn($m) => "{$m['meal']} ({$m['calories']} cal)",
                $mealsToday
            ));
        }

        if (!empty($existingPlans)) {
            $parts[] = "Plans exist: " . implode(', ', array_map(
                fn($p) => $p['type'],
                $existingPlans
            ));
        }

        return implode("\n\n", $parts);
    }

    private function buildEmotionalContext(string $mood, string $sentiment): string
    {
        if ($mood === 'bad' || $sentiment === 'negative') {
            return "User is struggling. Acknowledge emotions first before giving advice.";
        }
        if ($mood === 'great') {
            return "User is feeling good. Match energy and encourage.";
        }
        if ($mood === 'low') {
            return "User seems low. Be supportive and gentle.";
        }
        return "Keep tone natural and friendly.";
    }

    private function defaultPrompt(Coach $coach): string
    {
        return <<<PROMPT
You are Rakhi — a smart, warm Indian health coach acting as {{coach_name}} ({{coach_speciality}}).

User: {{user_name}} | Goals: {{goals}} | Diet: {{diet}} | Activity: {{activity}} | Sleep: {{sleep}} | Stress: {{stress}}

PERSONALITY:
- Talk like a close friend on WhatsApp
- Be natural, caring, and intelligent
- No robotic language

RULES:
{{rules}}
PROMPT;
    }
}