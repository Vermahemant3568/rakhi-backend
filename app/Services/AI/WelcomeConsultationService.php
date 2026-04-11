<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Jobs\GenerateConsultationReport;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use App\Services\AI\MemoryExtractorService;
use Illuminate\Support\Facades\Log;

class WelcomeConsultationService
{
    public function __construct(
        private LLMRouter $llm,
        private MemoryExtractorService $memoryExtractor
    ) {}

    /**
     * First welcome message after onboarding — warm intro
     */
    public function getWelcomeMessage(User $user): string
    {
        $name    = $user->first_name ?? '';
        $nameStr = $name ? "Hey {$name}! 🌸" : "Hey! 🌸";

        return "{$nameStr} I'm Rakhi — your personal health coach. So glad you're here!";
    }

    /**
     * Second message — call invite with chat fallback
     */
    public function getCallInviteMessage(): string
    {
        return "I'm calling you right now on the app 📞 — pick up and we'll have a quick chat so I can understand your lifestyle and create a plan that actually works for you.\n\nIf the call doesn't connect or you'd prefer to chat here, just type and we'll do it this way too — totally fine! 😊";
    }

    public function getVoiceWelcomeMessage(User $user): string
    {
        $name    = $user->first_name ?? '';
        $nameStr = $name ? "Hey {$name}!" : "Hey!";

        return "{$nameStr} I'm Rakhi, your health coach. I'm so glad we could connect! " .
               "I just want to have a real conversation with you — understand your daily routine, what's been going on, and then build a plan that actually fits your life. " .
               "So tell me honestly — how have you been feeling lately?";
    }

    /**
     * Goal-aware chat opener — shown when user declines the call.
     * Acknowledges their specific condition before asking anything.
     */
    public function getChatOpener(User $user): string
    {
        $name    = $user->first_name ?? '';
        $nameStr = $name ? $name : 'there';
        $goals   = $user->goals->pluck('name')->map(fn($g) => strtolower($g))->toArray();

        $goalStr = implode(' ', $goals);

        // Diabetes — most critical, acknowledge condition directly
        if (str_contains($goalStr, 'diabet')) {
            return "All good, let's do it here 😊\n\n" .
                   "Managing diabetes — especially Type 1 — is genuinely one of the harder things to navigate day to day. " .
                   "It's not just about food, it's about timing, energy, stress, sleep — everything connects.\n\n" .
                   "Before I build your plan, I want to actually understand your life. So tell me {$nameStr} — how long have you been managing this, and what feels hardest right now?";
        }

        // PCOS / thyroid / hormonal
        if (str_contains($goalStr, 'pcos') || str_contains($goalStr, 'thyroid') || str_contains($goalStr, 'period')) {
            return "No worries, let's chat here 😊\n\n" .
                   "PCOS and thyroid issues are so much more than just a diagnosis — they affect your energy, mood, weight, everything. And honestly, most plans people get don't account for that at all.\n\n" .
                   "I want to understand what's actually going on for you {$nameStr}. What's been bothering you the most lately?";
        }

        // Weight loss
        if (str_contains($goalStr, 'weight')) {
            return "Let's do it here 😊\n\n" .
                   "Weight loss is one of those things where everyone has advice but very few people actually understand what's going on in your specific life.\n\n" .
                   "So before anything else — tell me {$nameStr}, what have you already tried, and what made it hard to stick to?";
        }

        // Fitness / energy
        if (str_contains($goalStr, 'fitness') || str_contains($goalStr, 'energy')) {
            return "All good, let's chat here 😊\n\n" .
                   "Tell me {$nameStr} — what does your energy feel like on a typical day? Like when do you feel good and when does it drop?";
        }

        // Default — general wellness
        return "All good, let's do it here 😊\n\n" .
               "Tell me {$nameStr} — what's been going on with your health lately? Just talk to me like you'd tell a friend.";
    }

    /**
     * Main AI response handler
     */
    public function getConsultationResponse(
        ChatSession $session,
        User $user,
        string $userMessage,
        bool $voice = false
    ): string {
        $user->loadMissing(['goals', 'language']);

        $name     = $user->first_name ?? 'there';
        $goals    = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
        $age      = $user->age() > 0 ? $user->age() . ' years old' : 'not specified';
        $gender   = $user->gender ?? 'not specified';
        $diet     = $user->diet_preference ?? 'not specified';
        $activity = $user->activity_level ?? 'not specified';
        $goalList = $user->goals->pluck('name')->map(fn($g) => strtolower($g))->toArray();
        $goalStr  = implode(' ', $goalList);

        // Build condition-specific context hint for the AI
        $conditionHint = '';
        if (str_contains($goalStr, 'diabet')) {
            $conditionHint = "IMPORTANT: This user has selected diabetes as their goal. They may be Type 1 or Type 2 — you do NOT know yet. Do NOT assume. Your FIRST priority is to acknowledge that managing diabetes is genuinely hard and ask them how long they've been dealing with it and what type. Only after they answer should you move to lifestyle questions. Never jump straight to food questions — that feels like a form, not a coach.";
        } elseif (str_contains($goalStr, 'pcos') || str_contains($goalStr, 'thyroid')) {
            $conditionHint = "IMPORTANT: This user has a hormonal condition (PCOS/thyroid). Acknowledge that these conditions are complex and affect everything — energy, mood, weight, cycles. Ask what's been bothering them most before asking about diet or activity.";
        } elseif (str_contains($goalStr, 'weight')) {
            $conditionHint = "IMPORTANT: This user wants to lose weight. Do NOT start with food questions. First ask what they've already tried and what made it hard — this builds trust and gives you real context.";
        }

        // Conversation history — last 8 messages only to keep prompt tight
        $history = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get()
            ->sortBy('created_at')
            ->map(fn($m) => [
                'role'    => $m->role === 'user' ? 'user' : 'assistant',
                'message' => $m->message,
            ])
            ->values()
            ->toArray();

        $userMessageCount = ChatMessage::where('session_id', $session->id)
            ->where('role', 'user')
            ->count();

        $missingFields   = $this->getMissingFields($history);
        $readyToGenerate = $userMessageCount >= 4 && empty($missingFields);

        $systemPrompt = $this->buildConsultationSystemPrompt(
            name: $name,
            goals: $goals,
            age: $age,
            gender: $gender,
            diet: $diet,
            activity: $activity,
            readyToGenerate: $readyToGenerate,
            missingFields: $missingFields,
            voice: $voice,
            conditionHint: $conditionHint
        );

        return $this->llm->chat($systemPrompt . "\n\nUSER: " . $userMessage, $history);
    }

    /**
     * All 4 lifestyle fields required before plan generation.
     */
    private const REQUIRED_FIELDS = [
        'diet'     => '/\b(eat|food|meal|diet|breakfast|lunch|dinner|snack|veg|non.?veg|roti|rice|dal)\b/i',
        'activity' => '/\b(exercise|walk|gym|yoga|workout|active|sedentary|run|sport|steps|move)\b/i',
        'sleep'    => '/\b(sleep|slept|hours|rest|insomnia|wake|bed|night)\b/i',
        'stress'   => '/\b(stress|anxious|anxiety|pressure|challenge|problem|tension|overwhelm|relax|calm|busy|hectic)\b/i',
    ];

    /**
     * Returns array of missing field labels, or empty array if all covered.
     */
    public function getMissingFields(array $history): array
    {
        $text    = strtolower(implode(' ', array_column($history, 'message')));
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field => $pattern) {
            if (!preg_match($pattern, $text)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Check if enough info collected — ALL 4 fields must be present.
     */
    public function hasEnoughContext(array $history, int $userMessageCount): bool
    {
        if ($userMessageCount < 4) return false;

        return empty($this->getMissingFields($history));
    }

    public function shouldGeneratePlans(ChatSession $session): bool
    {
        $history = ChatMessage::where('session_id', $session->id)->get();
        $count   = $history->where('role', 'user')->count();
        $mapped  = $history->map(fn($m) => ['message' => $m->message])->toArray();

        return $this->hasEnoughContext($mapped, $count);
    }

    /**
     * Completion message (fixed - no fake lines)
     */
    public function getCompletionMessage(string $firstName = ''): string
    {
        $nameStr = $firstName ? ", {$firstName}" : '';

        return "Got it{$nameStr} — this gives me a good understanding of your routine. 💛\n\n" .
               "I’ll now create a plan that actually fits your lifestyle.\n\n" .
               "Give me a few seconds... 🎯";
    }

    /**
     * Generate plans — called as a background job after consultation.
     * Uses dispatch() instead of dispatchSync() to avoid blocking.
     */
    public function generateAllPlans(User $user, int $sessionId): void
    {
        $user->update(['first_consultation_complete' => true]);

        try {
            $conversation = ChatMessage::where('session_id', $sessionId)
                ->orderBy('id')
                ->get()
                ->map(fn($m) => ucfirst($m->role) . ': ' . $m->message)
                ->join("\n");

            $this->memoryExtractor->extractFromConversation($user, $conversation);
        } catch (\Exception $e) {
            Log::warning('Consultation memory extraction failed (non-fatal): ' . $e->getMessage());
        }

        foreach ([
            fn() => GenerateConsultationReport::dispatch($user, $sessionId),
            fn() => GenerateDietPlan::dispatch($user, $sessionId),
            fn() => GenerateFitnessPlan::dispatch($user, $sessionId),
        ] as $job) {
            try {
                $job();
            } catch (\Exception $e) {
                Log::error('Plan generation job failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Core AI brain prompt
     */
    private function buildConsultationSystemPrompt(
        string $name,
        string $goals,
        string $age,
        string $gender,
        string $diet,
        string $activity,
        bool $readyToGenerate,
        array $missingFields,
        bool $voice,
        string $conditionHint = ''
    ): string {

        $mode = $voice
            ? 'Voice mode: Keep replies to 1 short sentence. No lists. Speak naturally like a real phone call.'
            : 'Chat mode: Write complete, natural sentences. Never cut a sentence short. If your reply needs more than 2 sentences, split with a blank line — but ALWAYS finish every sentence completely.';

        if ($readyToGenerate) {
            $generateInstruction =
                "You now have all required information (diet, activity, sleep, stress).\n" .
                "Write 2–3 warm sentences summarising what you understood about the user, then tell them you are creating their personalised plan now.\n" .
                "End your message with exactly: [GENERATE_PLANS]";
        } else {
            $fieldQuestions = [
                'diet'     => 'What do you usually eat in a full day? Like breakfast, lunch, dinner — whatever is normal for you.',
                'activity' => 'How active are you day to day — do you exercise, go for walks, or is it mostly sitting?',
                'sleep'    => 'How many hours do you sleep at night, and do you feel rested when you wake up?',
                'stress'   => "What's been the biggest challenge or stress in your life lately?",
            ];

            $nextField = $missingFields[0] ?? null;
            $hint = $nextField
                ? "Still need to collect: " . implode(', ', $missingFields) . ".\n" .
                  "Ask about '{$nextField}' next. Suggested phrasing: \"{$fieldQuestions[$nextField]}\"\n" .
                  "Adapt naturally — do NOT copy word for word. React to what the user said first, then ask."
                : 'Continue the conversation naturally and ask ONE relevant follow-up question.';

            $generateInstruction =
                "DO NOT generate plans yet. DO NOT output [GENERATE_PLANS].\n" .
                "You must collect all 4 fields before generating: diet, activity, sleep, stress.\n" .
                $hint;
        }

        return <<<PROMPT
You are Rakhi — a warm, intelligent Indian health coach having a real heart-to-heart conversation with {$name}.

Your job right now is NOT to give advice — it is to UNDERSTAND the user deeply, build trust, and make them feel heard. Think of this as a first call with a new client.

USER PROFILE:
Name: {$name} | Goals: {$goals}
Age: {$age} | Gender: {$gender} | Diet: {$diet} | Activity: {$activity}

---

{$conditionHint}

---

TRUST-BUILDING RULES (most important):
- Make the user feel like you genuinely care about THEM, not just their data
- Reference what they just said specifically — never give a generic reply
- Share a small relatable observation when relevant ("A lot of people with your goal struggle with exactly that")
- If they share something personal or difficult, acknowledge it warmly before moving on
- Never rush — let the conversation breathe

---

HUMAN-LIKE RESPONSE STRUCTURE:
1. React specifically to what the user JUST said
2. Add a small natural insight or empathetic observation if relevant
3. Ask ONE follow-up question naturally

Example:
User: "I eat outside a lot, no time to cook"
BAD: "What do you eat in a day?"
GOOD: "Eating outside every day is actually more common than people think — and it doesn't have to be a problem, we just need to work with it.\n\nWhat do you usually end up ordering most often?"

---

VOICE / CALL FALLBACK:
- If the user mentions the call didn't connect, had issues, or they prefer to chat — immediately say something like: "No worries at all! Let's just do it here — same thing, just typing. So tell me..."
- Then continue the consultation naturally in chat
- Never make the user feel bad about not taking the call

---

NO GENERIC LANGUAGE — NEVER say:
- "Thank you for sharing"
- "I understand"
- "That helps me understand"
- "Great!", "Absolutely!", "Sure!", "Of course!"
Always respond specifically to what the user said.

---

COMPLETE SENTENCES — CRITICAL:
- Every sentence must be fully complete before ending your reply
- Never stop mid-thought or mid-sentence
- If you are running long, finish the current sentence and move the next thought to a new line
- Re-read your reply before sending — if any sentence feels cut off, complete it

---

EMOTIONAL INTELLIGENCE:
- If user sounds stressed, tired, or overwhelmed → acknowledge it first, warmly
- Example: "That honestly sounds exhausting — managing all of that while trying to stay healthy is a lot."
- Only then move to the next question

---

NATURAL FLOW:
- Do NOT jump question-to-question like a form
- React first, then ask
- Never ask more than ONE question per message
- No bullet points, no lists, no headers

---

{$mode}

{$generateInstruction}
PROMPT;
    }
}