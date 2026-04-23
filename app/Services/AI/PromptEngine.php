<?php

namespace App\Services\AI;

use App\Models\Coach;
use App\Models\PromptTemplate;
use App\Models\RakhiRule;
use App\Models\User;
use App\Services\NLP\LanguageDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;
use Illuminate\Support\Facades\Cache;

class PromptEngine
{
    public function __construct(
        private LanguageDetector $languageDetector,
        private MoodAnalyzer $moodAnalyzer,
        private SentimentAnalyzer $sentimentAnalyzer,
    ) {}

    public function buildSystemPrompt(
        User $user,
        Coach $coach,
        string $userMessage = '',
        string $sessionLanguage = 'en',
        bool $isReturning = false,
        string $lastInteractionSummary = '',
        string $lastAiResponse = ''
    ): string {
        $user->loadMissing(['goals', 'language']);

        $firstName   = $user->first_name ?? 'there';
        $age         = $user->age ?? '';
        $gender      = $user->gender ?? '';
        $weight      = $user->weight ?? '';
        $height      = $user->height ?? '';
        $goals       = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
        $primaryGoal = $user->goals->pluck('name')->first() ?? 'general wellness';
        $diet        = $user->diet_preference ?? 'not specified';

        // Detect language
        $langCode = $userMessage ? $this->languageDetector->detect($userMessage) : 'en';
        if ($langCode === 'en' && $sessionLanguage !== 'en') {
            $langCode = $sessionLanguage;
        }
        $langInstruction = $this->languageDetector->getLanguageInstruction($langCode);

        // Detect emotion
        $mood      = $userMessage ? $this->moodAnalyzer->analyze($userMessage) : 'okay';
        $sentiment = $userMessage ? $this->sentimentAnalyzer->analyze($userMessage) : 'neutral';
        $emotionLine = $this->buildEmotionalContext($mood, $sentiment);

        // Load active RakhiRules
        $rules = $this->loadActiveRules($coach->id);

        // Try to load prompt from DB (cached per coach)
        $template = $this->resolveTemplate($coach->id);

        if ($template) {
            $content = $this->injectVariables($template->content, [
                '{{user_name}}'                  => $firstName,
                '{{primary_goal}}'               => $primaryGoal,
                '{{age}}'                        => $age,
                '{{gender}}'                     => $gender,
                '{{weight}}'                     => $weight,
                '{{height}}'                     => $height,
                '{{goals}}'                      => $goals,
                '{{diet}}'                       => $diet,
                '{{language}}'                   => $langInstruction,
                '{{rules}}'                      => $rules,
                '{{coach_name}}'                 => $coach->name ?? 'Health Coach',
                '{{coach_speciality}}'           => $coach->speciality ?? 'general health',
                '{{is_returning}}'               => $isReturning ? 'returning user' : 'new user',
                '{{last_interaction_summary}}'   => $lastInteractionSummary ?: 'No prior context.',
            ]);

            // Append dynamic emotion + language context that changes per message
            $antiRepeat = $this->buildAntiRepetitionBlock($lastAiResponse);
            return $content . "\n\n━━━━━━━━━━━ CURRENT MESSAGE CONTEXT ━━━━━━━━━━━\n\nEmotion: {$emotionLine}\nLanguage: {$langInstruction}{$antiRepeat}";
        }

        // Fallback: hardcoded prompt
        $returningNote = $isReturning ? "\n- This is a RETURNING user. Do NOT use first-time greetings." : '';
        $lastCtx = $lastInteractionSummary ? "\nLast interaction: {$lastInteractionSummary}" : '';
        return $this->hardcodedSystemPrompt($firstName, $primaryGoal, $goals, $emotionLine, $langInstruction, $returningNote, $lastCtx, $lastAiResponse);
    }

    public function buildUserPrompt(
        string $userMessage,
        array $memories,
        array $knowledgeContext,
        ?array $checkin = null,
        array $mealsToday = [],
        array $existingPlans = [],
        array $structuredMemory = [],
        array $crossSessionHistory = [],
        array $consultationNotes = [],
        string $lastAiResponse = ''
    ): string {

        $parts = [];

        // 0. Anti-repetition: show last AI response so LLM avoids repeating it
        if (!empty($lastAiResponse)) {
            $parts[] = "Last response:\n" . $this->shorten($lastAiResponse, 300) . "\n\nInstruction: Do NOT repeat, reuse, or echo anything from the last response above. Use different wording, a new angle, or ask a follow-up question.";
        }

        // 1. User's health context (only priority keys, already filtered by ContextBuilder)
        if (!empty($structuredMemory)) {
            $lines = [];
            foreach ($structuredMemory as $k => $v) {
                if (!empty($v)) {
                    $label   = str_replace('_', ' ', $k);
                    $lines[] = "{$label}: {$this->shorten($v)}";
                }
            }
            if (!empty($lines)) {
                $parts[] = 'About this user: ' . implode(' | ', $lines);
            }
        }

        // 2. User's message — always first, highest priority
        $parts[] = "User: {$userMessage}";

        // 3. Relevant past memory (max 2, already filtered by ContextBuilder)
        if (!empty($memories)) {
            $parts[] = 'Relevant history: ' . implode(' | ', array_slice($memories, 0, 2));
        }

        // 4. Coach knowledge (max 1 snippet)
        if (!empty($knowledgeContext)) {
            $parts[] = 'Reference: ' . $knowledgeContext[0];
        }

        // 5. Today's checkin (only if available and message is complex)
        if ($checkin) {
            $checkinParts = ["mood: {$checkin['mood']}", "energy: {$checkin['energy']}"];
            if (!empty($checkin['sleep'])) {
                $checkinParts[] = "sleep: {$checkin['sleep']}h";
            }
            $parts[] = 'Today: ' . implode(', ', $checkinParts);
        }

        // 6. Meals (only if meal-related, already filtered by ContextBuilder)
        if (!empty($mealsToday)) {
            $meals   = array_map(fn($m) => $m['meal'], array_slice($mealsToday, 0, 2));
            $parts[] = 'Meals today: ' . implode(', ', $meals);
        }

        // 7. Existing plans (only if plan-related, already filtered by ContextBuilder)
        if (!empty($existingPlans)) {
            $parts[] = 'Has plans: ' . implode(', ', array_column($existingPlans, 'type'));
        }

        return implode("\n", $parts);
    }

    private function shorten(string $text): string
    {
        return strlen($text) > 150 ? substr($text, 0, 150) . '...' : $text;
    }

    // ─────────────────────────────────────────────
    // DB TEMPLATE RESOLVER (CACHED)
    // ─────────────────────────────────────────────

    private function resolveTemplate(int $coachId): ?PromptTemplate
    {
        return Cache::remember("prompt_template_coach_{$coachId}", 300, function () use ($coachId) {
            return PromptTemplate::where('coach_id', $coachId)
                ->where('template_type', 'system_prompt')
                ->where('is_active', true)
                ->orderBy('version', 'desc')
                ->first();
        });
    }

    // ─────────────────────────────────────────────
    // VARIABLE INJECTION
    // ─────────────────────────────────────────────

    private function injectVariables(string $content, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    // ─────────────────────────────────────────────
    // ACTIVE RULES LOADER
    // ─────────────────────────────────────────────

    private function loadActiveRules(int $coachId): string
    {
        $rules = Cache::remember("rakhi_rules_{$coachId}", 300, function () use ($coachId) {
            return RakhiRule::where('is_active', true)
                ->where(function ($q) use ($coachId) {
                    $q->whereNull('applies_to_coaches')
                      ->orWhereJsonContains('applies_to_coaches', $coachId);
                })
                ->orderBy('priority', 'desc')
                ->pluck('rule_content')
                ->toArray();
        });

        return !empty($rules) ? implode("\n- ", $rules) : 'Always be warm, human, and helpful.';
    }

    // ─────────────────────────────────────────────
    // FALLBACK HARDCODED PROMPT
    // ─────────────────────────────────────────────

    private function hardcodedSystemPrompt(
        string $firstName,
        string $primaryGoal,
        string $goals,
        string $emotionLine,
        string $langInstruction,
        string $returningNote = '',
        string $lastCtx = '',
        string $lastAiResponse = ''
    ): string {
        $antiRepeat = $this->buildAntiRepetitionBlock($lastAiResponse);
        return <<<PROMPT
You are Rakhi — a warm, knowledgeable Indian health coach. Professional, empathetic, human.

User: {$firstName} | Goal: {$primaryGoal}{$lastCtx}

RULES:{$returningNote}
- Acknowledge before advising. Empathy first, practical second.
- ONE question per reply. 2–3 sentences max.
- Never repeat the same opener or structure as your last response.
- Never use bullet points, headers, or numbered lists in replies.
- Never say: "Absolutely!", "Great question!", "Thank you for sharing", "I understand your concern".
- Reference what you already know about the user before asking for new info.
- Sound like a real person, not a health website.

EMOTION: {$emotionLine}

LANGUAGE: {$langInstruction}
{$antiRepeat}
PROMPT;
    }

    private function buildAntiRepetitionBlock(string $lastAiResponse): string
    {
        if (empty(trim($lastAiResponse))) return '';
        $preview = $this->shorten($lastAiResponse, 150);
        return "\n\nLAST RESPONSE (do NOT repeat or echo): \"{$preview}\"";
    }

    private function buildEmotionalContext(string $mood, string $sentiment): string
    {
        $emotion = match(true) {
            $mood === 'sad'       || $sentiment === 'negative' => 'sad',
            $mood === 'stressed'                               => 'stressed',
            $mood === 'tired'                                  => 'tired',
            $mood === 'great'    || $mood === 'happy'
                                 || $sentiment === 'positive' => 'positive',
            default                                            => 'neutral',
        };

        return match($emotion) {
            'sad'      => 'User is sad/low. Acknowledge their feeling FIRST before any advice. One gentle question only.',
            'stressed' => 'User is stressed/overwhelmed. Acknowledge the pressure first. ONE calming thought, then one question.',
            'tired'    => 'User is tired/drained. Acknowledge fatigue first. Ask what is causing it before giving tips.',
            'positive' => 'User is in a good mood. Match their energy briefly, then build on it with one useful thought.',
            default    => 'Neutral mood. Respond warmly and naturally. One follow-up question.',
        };
    }

    // ─────────────────────────────────────────────
    // CACHE CLEAR (call from admin when template updated)
    // ─────────────────────────────────────────────

    public static function clearTemplateCache(int $coachId): void
    {
        Cache::forget("prompt_template_coach_{$coachId}");
        Cache::forget("rakhi_rules_{$coachId}");
    }
}
