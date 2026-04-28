<?php

namespace App\Services\AI;

use App\Models\User;
use App\Models\UserMemory;
use Illuminate\Support\Facades\Log;

class MemoryExtractorService
{
    public function __construct(private LLMRouter $llm) {}

    // ─────────────────────────────────────────────
    // SINGLE MESSAGE EXTRACTION
    // ─────────────────────────────────────────────

    public function extractAndStore(User $user, string $userMessage): void
    {
        try {
            $keys   = implode(', ', UserMemory::KEYS);
            $prompt = $this->buildExtractionPrompt($userMessage, $keys);

            $raw   = $this->llm->chat($prompt);
            $facts = $this->parseJson($raw);

            if (empty($facts)) return;

            foreach ($facts as $key => $value) {

                if (!in_array($key, UserMemory::KEYS)) continue;

                $cleanValue = trim((string) $value);

                // Skip useless values
                if ($cleanValue === '' || strlen($cleanValue) < 3) continue;

                UserMemory::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    [
                        'value'  => $cleanValue,
                        'source' => 'chat'
                    ]
                );
            }

        } catch (\Exception $e) {
            Log::warning('MemoryExtractor failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // FULL CONVERSATION EXTRACTION
    // ─────────────────────────────────────────────

    public function extractFromConversation(User $user, string $fullConversation): void
    {
        try {
            $keys   = implode(', ', UserMemory::KEYS);
            $prompt = $this->buildBulkExtractionPrompt($fullConversation, $keys);

            $raw   = $this->llm->chat($prompt);
            $facts = $this->parseJson($raw);

            if (empty($facts)) return;

            foreach ($facts as $key => $value) {

                if (!in_array($key, UserMemory::KEYS)) continue;

                $cleanValue = trim((string) $value);

                if ($cleanValue === '' || strlen($cleanValue) < 3) continue;

                UserMemory::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    [
                        'value'  => $cleanValue,
                        'source' => 'consultation'
                    ]
                );
            }

        } catch (\Exception $e) {
            Log::warning('MemoryExtractor bulk failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // PROMPT (SMART + HINGLISH AWARE 🔥)
    // ─────────────────────────────────────────────

    private function buildExtractionPrompt(string $message, string $keys): string
    {
        return <<<PROMPT
You extract structured user facts for a health AI.

User may speak in Hinglish (Hindi + English mix). Understand both.

Extract ONLY clearly stated facts. Do NOT guess.

Valid keys: {$keys}

Understand real-life language:
- "raat ko late khata hoon" → late night eating
- "bahar ka khata hoon" → eats outside food
- "office pressure hai" → work stress
- "thak jata hoon" → fatigue / low energy
- "type 1 hai mujhe" or "t1d" or "insulin pe hoon" → diabetes_type: type1
- "type 2 hai" or "t2d" or "metformin leta hoon" → diabetes_type: type2
- "gestational diabetes" or "pregnancy mein sugar" → diabetes_type: gestational
- "prediabetes" or "borderline sugar" or "pre-diabetic" → diabetes_type: prediabetes
- "first trimester" or "week 8" or "8 weeks pregnant" → current_stage: first trimester
- "second trimester" or "week 20" → current_stage: second trimester
- "third trimester" or "week 35" → current_stage: third trimester
- "early weight loss" or "just started" or "1 hafte se" → current_stage: early phase
- "frustrated" or "koi fayda nahi" or "giving up" → emotional_state: frustrated
- "motivated" or "josh mein hoon" → emotional_state: motivated
- "anxious" or "ghabra raha" → emotional_state: anxious
- "nahi follow kar paya" or "skipped" or "bhool gaya" → adherence_pattern: struggling
- "roz kar raha hoon" or "consistent" → adherence_pattern: consistent
- "thoda better" or "improving" → adherence_pattern: improving

For diabetes_type: only store one of: type1, type2, gestational, prediabetes
For emotional_state: only store one of: motivated, frustrated, anxious, low energy, sad, stressed, okay
For adherence_pattern: only store one of: consistent, struggling, improving, irregular

Message:
"{$message}"

Rules:
- Only extract what user clearly said
- Keep values short (max 12 words)
- No full sentences
- No assumptions

Return ONLY JSON:
{"diet_habit":"eats outside daily"}

If nothing → {}
PROMPT;
    }

    private function buildBulkExtractionPrompt(string $conversation, string $keys): string
    {
        return <<<PROMPT
You extract structured memory from a full health consultation.

User may speak in Hinglish.

Focus ONLY on user messages.

Valid keys: {$keys}

Conversation:
{$conversation}

Rules:
- Extract only clear facts (no guessing)
- Prefer latest info if conflict
- Keep values short (max 15 words)
- Capture patterns (late eating, low activity, stress etc.)
- For diabetes_type: detect from signals like "type 1", "t1d", "insulin dependent", "type 2", "t2d", "metformin", "gestational", "prediabetes"
- diabetes_type value must be one of: type1, type2, gestational, prediabetes
- For current_stage: detect trimester (first/second/third), weight loss phase (early/mid/plateau), PCOS stage, etc.
- For emotional_state: detect from tone and words — one of: motivated, frustrated, anxious, low energy, sad, stressed, okay
- For adherence_pattern: detect from consistency signals — one of: consistent, struggling, improving, irregular

Return ONLY JSON like:
{
 "diabetes_type":"type2",
 "current_stage":"early weight loss phase",
 "emotional_state":"frustrated",
 "adherence_pattern":"struggling",
 "diet_habit":"late night eating, outside food",
 "activity_level":"very low, mostly sitting",
 "sleep_pattern":"5-6 hours, poor sleep",
 "stress_level":"high work pressure"
}

No explanation. No extra text.
PROMPT;
    }

    // ─────────────────────────────────────────────
    // JSON CLEANER (MORE ROBUST 🔥)
    // ─────────────────────────────────────────────

    private function parseJson(string $raw): array
    {
        if (empty($raw)) return [];

        // Remove markdown
        $clean = preg_replace('/```json|```/', '', $raw);
        $clean = trim($clean);

        // Extract JSON safely
        $start = strpos($clean, '{');
        $end   = strrpos($clean, '}');

        if ($start === false || $end === false) return [];

        $json = substr($clean, $start, $end - $start + 1);

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
