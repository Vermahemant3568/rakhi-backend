<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Jobs\GenerateConsultationReport;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use Illuminate\Support\Facades\Log;

class WelcomeConsultationService
{
    public function __construct(private LLMRouter $llm) {}

    /**
     * Warm, human first message shown when user enters chat after onboarding.
     * NOT a scripted question — just a genuine greeting that opens the conversation.
     */
    public function getWelcomeMessage(User $user): string
    {
        $name  = $user->first_name ?? '';
        $goals = $user->goals->pluck('name')->join(', ') ?: 'feeling your best';

        $nameStr = $name ? "Hi {$name}! 🌸" : "Hey there! 🌸";

        return "{$nameStr} I'm Rakhi — your personal health companion.\n\n" .
               "I can see you want to work on {$goals}. I love that! " .
               "Before I create your personalized plan, I'd love to get to know you a little better — " .
               "your routine, what you eat, how you move, and what's been challenging.\n\n" .
               "So tell me — how are you feeling today? And what's been going on with your health lately? 😊";
    }

    public function getVoiceWelcomeMessage(User $user): string
    {
        $name  = $user->first_name ?? '';
        $goals = $user->goals->pluck('name')->join(', ') ?: 'your wellness goals';

        $nameStr = $name ? "Hi {$name}!" : "Hey!";

        return "{$nameStr} I am Rakhi, your personal health companion. " .
               "I can see you want to work on {$goals} — that is wonderful! " .
               "I would love to have a quick chat to understand your lifestyle and create your personalized plan. " .
               "So tell me — how are you feeling today, and what has been going on with your health lately?";
    }

    /**
     * LLM-driven consultation response.
     * Rakhi reads what the user actually said, responds naturally,
     * and decides when she has enough to generate plans.
     */
    public function getConsultationResponse(
        ChatSession $session,
        User $user,
        string $userMessage,
        bool $voice = false
    ): string {
        $user->loadMissing(['goals', 'language']);

        $name        = $user->first_name ?? 'there';
        $goals       = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
        $age         = $user->age() > 0 ? $user->age() . ' years old' : 'age not specified';
        $gender      = $user->gender ?? 'not specified';
        $weight      = $user->weight ? $user->weight . ' kg' : 'not specified';
        $height      = $user->height ? $user->height . ' cm' : 'not specified';
        $diet        = $user->diet_preference ?? 'not specified';
        $activity    = $user->activity_level ?? 'not specified';
        $stress      = $user->stress_level ?? 'not specified';
        $sleep       = $user->sleep_hours ? $user->sleep_hours . ' hrs/night' : 'not specified';

        // Build conversation history for this session
        $history = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'role'    => $m->role === 'user' ? 'user' : 'assistant',
                'message' => $m->message,
            ])
            ->toArray();

        $userMessageCount = ChatMessage::where('session_id', $session->id)
            ->where('role', 'user')
            ->count();

        $readyToGenerate = $this->hasEnoughContext($history, $userMessageCount);

        $systemPrompt = $this->buildConsultationSystemPrompt(
            name: $name,
            goals: $goals,
            age: $age,
            gender: $gender,
            weight: $weight,
            height: $height,
            diet: $diet,
            activity: $activity,
            stress: $stress,
            sleep: $sleep,
            readyToGenerate: $readyToGenerate,
            voice: $voice
        );

        $response = $this->llm->chat($systemPrompt . "\n\nUSER: " . $userMessage, $history);

        return $response;
    }

    /**
     * Determine if Rakhi has gathered enough context to generate plans.
     * Checks for coverage of: routine, diet, activity, challenges — not just message count.
     */
    public function hasEnoughContext(array $history, int $userMessageCount): bool
    {
        if ($userMessageCount < 3) return false;
        if ($userMessageCount >= 6) return true;

        // Check if key topics have been covered in the conversation
        $allText = strtolower(implode(' ', array_column($history, 'message')));

        $covered = 0;
        if (preg_match('/\b(morning|wake|routine|day|schedule|work|office|home)\b/', $allText)) $covered++;
        if (preg_match('/\b(eat|food|breakfast|lunch|dinner|meal|diet|drink|water|khana|khaana)\b/', $allText)) $covered++;
        if (preg_match('/\b(exercise|walk|gym|yoga|workout|active|sit|sedentary|kasrat)\b/', $allText)) $covered++;
        if (preg_match('/\b(challenge|problem|issue|struggle|difficult|hard|stress|busy|time|nahi|nhi)\b/', $allText)) $covered++;

        return $covered >= 3;
    }

    public function shouldGeneratePlans(ChatSession $session): bool
    {
        $history = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'message' => $m->message])
            ->toArray();

        $userMessageCount = ChatMessage::where('session_id', $session->id)
            ->where('role', 'user')
            ->count();

        return $this->hasEnoughContext($history, $userMessageCount);
    }

    public function getCompletionMessage(string $firstName = ''): string
    {
        $nameStr = $firstName ? ", {$firstName}" : '';

        return "Thank you so much for sharing all of this with me{$nameStr}! 💖\n\n" .
               "I now have a really clear picture of your lifestyle, goals, and what you're working through. " .
               "Give me just a moment while I put together your personalized:\n\n" .
               "✅ Health Consultation Report\n" .
               "✅ Custom Diet Plan\n" .
               "✅ Personalized Fitness Plan\n\n" .
               "I'll send them to you in just a few seconds! 🎯";
    }

    public function generateAllPlans(User $user, int $sessionId): void
    {
        try {
            $user->update(['first_consultation_complete' => true]);

            GenerateConsultationReport::dispatchSync($user, $sessionId);
            GenerateDietPlan::dispatchSync($user, $sessionId);
            GenerateFitnessPlan::dispatchSync($user, $sessionId);
        } catch (\Exception $e) {
            Log::error('generateAllPlans failed: ' . $e->getMessage());
        }
    }

    private function buildConsultationSystemPrompt(
        string $name,
        string $goals,
        string $age,
        string $gender,
        string $weight,
        string $height,
        string $diet,
        string $activity,
        string $stress,
        string $sleep,
        bool $readyToGenerate,
        bool $voice
    ): string {
        $generateInstruction = $readyToGenerate
            ? "You now have enough information about this person. " .
              "Wrap up the conversation warmly — thank them genuinely for sharing, " .
              "tell them you're now creating their personalized Health Report, Diet Plan, and Fitness Plan. " .
              "End with excitement and encouragement. Keep it to 3-4 sentences max. " .
              "IMPORTANT: End your message with exactly this tag on a new line: [GENERATE_PLANS]"
            : "You do NOT yet have enough information. Keep the conversation going naturally. " .
              "Ask about whichever of these topics hasn't been covered yet (pick ONE): " .
              "daily routine & schedule, eating habits & diet, physical activity & exercise, " .
              "biggest health challenges & what success looks like. " .
              "Ask only ONE question. Make it feel like a natural follow-up to what they just said.";

        $voiceNote = $voice
            ? "This is a VOICE conversation. Keep responses short (2-3 sentences max). No emojis. Speak naturally."
            : "This is a TEXT/CHAT conversation. Keep it warm and conversational. Use emojis sparingly.";

        return <<<PROMPT
You are Rakhi — a warm, caring Indian health companion having a first consultation conversation with a new user.
Your goal is to understand their lifestyle well enough to create a personalized health plan.

ABOUT THIS PERSON (from their profile):
- Name: {$name}
- Goals: {$goals}
- Age: {$age}, Gender: {$gender}
- Weight: {$weight}, Height: {$height}
- Diet preference: {$diet}
- Activity level: {$activity}
- Stress level: {$stress}
- Sleep: {$sleep}

YOUR PERSONALITY IN THIS CONSULTATION:
- Talk like a caring friend who genuinely wants to understand them — not a doctor filling a form
- Be warm, curious, and encouraging
- React to what they actually say before asking the next question
- If they give a vague or incomplete answer, gently ask for more detail in a natural way
- If they seem stressed or struggling, acknowledge it with empathy first
- NEVER use bullet points or numbered lists
- NEVER ask multiple questions at once — ONE question at a time
- NEVER repeat a question they've already answered
- Keep responses to 3-5 sentences max
- Use their first name naturally but sparingly (not every message)
- NEVER start with "Absolutely!", "Certainly!", "Great!", "Sure!" or similar filler words
- Respond in whatever language they write in (Hindi, Hinglish, Tamil, Telugu, English)

{$voiceNote}

WHAT TO DO NOW:
{$generateInstruction}
PROMPT;
    }
}
