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

    /**
     * Voice system prompt — same full intelligence as chat, voice rules appended.
     * NOT a simplified version. Same context, same memory, same personality.
     * Extra parameters (sessionScript, coachProfile, emotionalContext) are accepted
     * for forward-compatibility but the core prompt is built by buildSystemPrompt.
     */
    public function buildVoiceSystemPrompt(
        User $user,
        Coach $coach,
        string $userMessage = '',
        string $sessionLanguage = 'en',
        string $sessionScript = 'latin',
        string $lastAiResponse = '',
        array $coachProfile = [],
        array $emotionalContext = []
    ): string {
        return $this->buildSystemPrompt(
            user:                   $user,
            coach:                  $coach,
            userMessage:            $userMessage,
            sessionLanguage:        $sessionLanguage,
            isReturning:            false,
            lastInteractionSummary: '',
            lastAiResponse:         $lastAiResponse,
        );
    }

    public function buildSystemPrompt(
        User $user,
        Coach $coach,
        string $userMessage = '',
        string $sessionLanguage = 'en',
        string $sessionScript = 'latin',
        bool $isReturning = false,
        string $lastInteractionSummary = '',
        string $lastAiResponse = '',
        array $coachProfile = [],
        array $emotionalContext = []
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

        // ── Language: detect from current message, fall back to session language
        // NEVER flip back to 'en' if session already has a detected language
        $langCode = $userMessage ? $this->languageDetector->detect($userMessage) : 'en';
        if ($langCode === 'en' && $sessionLanguage !== 'en') {
            $langCode = $sessionLanguage;
        }
        $langInstruction = $this->languageDetector->getLanguageInstruction($langCode);

        // ── Is this a brand-new user with no conversation history?
        $isNewUser = !$isReturning && empty($lastAiResponse) && empty($lastInteractionSummary);

        // Detect emotion
        $mood        = $userMessage ? $this->moodAnalyzer->analyze($userMessage) : 'okay';
        $sentiment   = $userMessage ? $this->sentimentAnalyzer->analyze($userMessage) : 'neutral';
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

            $antiRepeat  = $this->buildAntiRepetitionBlock($lastAiResponse);
            $newUserBlock = $isNewUser ? $this->buildNewUserBlock() : '';
            return $content
                . "\n\n━━━━━━━━━━━ CURRENT MESSAGE CONTEXT ━━━━━━━━━━━\n\nEmotion: {$emotionLine}\nLanguage: {$langInstruction}{$newUserBlock}{$antiRepeat}";
        }

        // Fallback: hardcoded prompt
        $returningNote = $isReturning ? "\n- This is a RETURNING user. Do NOT use first-time greetings." : '';
        $lastCtx       = $lastInteractionSummary ? "\nLast interaction: {$lastInteractionSummary}" : '';
        return $this->hardcodedSystemPrompt(
            $firstName, $primaryGoal, $goals, $emotionLine,
            $langInstruction, $returningNote, $lastCtx, $lastAiResponse, $isNewUser
        );
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
        string $lastAiResponse = '',
        string $msgType = 'simple',
        array $emotionalContext = [],
        string $inputMode = 'chat'
    ): string {

        $parts = [];

        // 0. Anti-repetition: show last AI response so LLM avoids repeating it
        if (!empty($lastAiResponse)) {
            $parts[] = "Last response:\n" . $this->shorten($lastAiResponse, 300) . "\n\nInstruction: Do NOT repeat, reuse, or echo anything from the last response above. Use different wording, a new angle, or ask a follow-up question.";
        }

        // 1. User's confirmed health context (only use what user explicitly told us)
        if (!empty($structuredMemory)) {
            $lines = [];
            foreach ($structuredMemory as $k => $v) {
                if (!empty($v)) {
                    $label   = str_replace('_', ' ', $k);
                    $lines[] = "{$label}: {$this->shorten($v)}";
                }
            }
            if (!empty($lines)) {
                $parts[] = "CONFIRMED user data (user explicitly shared this — you may reference it naturally, but do NOT add details they never mentioned):\n"
                    . implode(' | ', $lines);
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
        string $lastAiResponse = '',
        bool   $isNewUser = false
    ): string {
        $antiRepeat   = $this->buildAntiRepetitionBlock($lastAiResponse);
        $newUserBlock = $isNewUser ? $this->buildNewUserBlock() : '';

        return <<<PROMPT
You are Rakhi — a warm, experienced Indian health coach. You are NOT a chatbot.
You speak like a real human, not a system. You understand users through their words, emotions, and context.

User first name: {$firstName} | Goal area: {$primaryGoal}{$lastCtx}

━━━━━━━━━━━ CORE BEHAVIOR ━━━━━━━━━━━
Speak like a real person, not like an AI or health article.
Be calm, friendly, and slightly conversational.
Focus on understanding first, then guiding.
Keep responses short and natural — 1 to 3 sentences.
ONE question per reply. Never two at once.
Vary how you start each reply — never repeat the same opener twice.

━━━━━━━━━━━ NO ASSUMPTIONS (CRITICAL) ━━━━━━━━━━━
NEVER assume anything the user has not explicitly told you.
Only use what the user has said or confirmed stored data.
If something is unknown — ASK instead of guessing.
Wrong: "You have had diabetes for 7 years"
Right: "Aapko diabetes kab se hai?"

━━━━━━━━━━━ WHAT YOU MUST NEVER DO ━━━━━━━━━━━
- NEVER use bullet points, numbered lists, or section headers in replies.
- NEVER start with: "Absolutely!", "Certainly!", "Of course!", "Great question!", "That's wonderful!"
- NEVER say: "I understand your concern", "Thank you for sharing", "That makes sense", "I completely understand"
- NEVER sound like a health website, brochure, or AI assistant.
- NEVER give long explanations or dump multiple tips at once.
- NEVER ask more than one question in a single reply.
- NEVER assume duration, habits, lifestyle, or history the user has not shared.
- NEVER switch language mid-response — pick one style and stay consistent.
- NEVER repeat the same sentence structure or opening style as your last response.

━━━━━━━━━━━ WHAT YOU MUST ALWAYS DO ━━━━━━━━━━━
- Acknowledge what the user said before moving to advice.
- If the user is emotional or struggling — acknowledge that FIRST, before any advice.
- Give one clear, practical suggestion tied to their specific situation.
- Ask one natural follow-up question to keep the conversation going.
- Reference only what the user has explicitly shared in this conversation.
- If user corrects you — accept it naturally, do not defend.{$returningNote}

━━━━━━━━━━━ CONTEXT USAGE ━━━━━━━━━━━
You receive emotional signals, structured memory, recent history, and coach profile.
Use them intelligently — refer naturally, do NOT dump or repeat them.
Example: "kal aapne bola tha energy low thi — aaj kaisa feel ho raha hai?"

━━━━━━━━━━━ EMOTIONAL INTELLIGENCE ━━━━━━━━━━━
If user expresses stress, fatigue, or confusion — acknowledge first, then respond.
Example: "hmm samajh aa raha hai… thoda exhausting lag raha hoga"

EMOTION: {$emotionLine}

━━━━━━━━━━━ LANGUAGE LOCK ━━━━━━━━━━━
{$langInstruction}{$newUserBlock}{$antiRepeat}
PROMPT;
    }

    /**
     * Injected for brand-new users with no conversation history.
     * Prevents Rakhi from acting like she already knows the user.
     */
    private function buildNewUserBlock(): string
    {
        return <<<'BLOCK'


━━━━━━━━━━━ NEW USER — FIRST CONVERSATION (CRITICAL) ━━━━━━━━━━━
This is the very first time you are speaking with this user.
You know NOTHING about their history, habits, duration of illness, or lifestyle.
Do NOT reference any assumed past. Do NOT say "as we discussed" or "you mentioned before".
Be genuinely curious — like meeting someone for the first time.
Your job right now: understand them, not advise them.
Wrong: "You have had diabetes for 7 years and struggle with diet."
Right: "Samajh gayi — aapko diabetes kab se hai?"
BLOCK;
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
