<?php

namespace App\Services\AI;

use App\Models\User;
use App\Models\UserMemory;
use Illuminate\Support\Facades\Log;

class MemoryExtractorService
{
    public function __construct(private LLMRouter $llm) {}

    /**
     * Extract structured memory facts from a user message and upsert into DB.
     * Called after every user message — non-blocking, wrapped in try/catch.
     */
    public function extractAndStore(User $user, string $userMessage): void
    {
        try {
            $keys    = implode(', ', UserMemory::KEYS);
            $prompt  = $this->buildExtractionPrompt($userMessage, $keys);
            $raw     = $this->llm->chat($prompt);
            $facts   = $this->parseJson($raw);

            if (empty($facts)) return;

            foreach ($facts as $key => $value) {
                if (!in_array($key, UserMemory::KEYS)) continue;
                if (empty(trim((string) $value)))       continue;

                UserMemory::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    ['value'   => trim($value), 'source' => 'chat']
                );
            }
        } catch (\Exception $e) {
            Log::warning('MemoryExtractor failed (non-fatal): ' . $e->getMessage());
        }
    }

    /**
     * Bulk extract from full consultation conversation — called once after consultation ends.
     */
    public function extractFromConversation(User $user, string $fullConversation): void
    {
        try {
            $keys   = implode(', ', UserMemory::KEYS);
            $prompt = $this->buildBulkExtractionPrompt($fullConversation, $keys);
            $raw    = $this->llm->chat($prompt);
            $facts  = $this->parseJson($raw);

            if (empty($facts)) return;

            foreach ($facts as $key => $value) {
                if (!in_array($key, UserMemory::KEYS)) continue;
                if (empty(trim((string) $value)))       continue;

                UserMemory::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    ['value'   => trim($value), 'source' => 'consultation']
                );
            }
        } catch (\Exception $e) {
            Log::warning('MemoryExtractor bulk failed (non-fatal): ' . $e->getMessage());
        }
    }

    private function buildExtractionPrompt(string $message, string $keys): string
    {
        return <<<PROMPT
You are a memory extraction system for a health coach AI.

Analyze this single user message and extract ONLY facts that are clearly stated.
Do NOT guess or infer. If nothing relevant is mentioned, return {}.

Valid memory keys: {$keys}

Key definitions:
- health_condition: any disease, condition, or diagnosis (e.g. "Type 2 diabetes", "PCOS", "thyroid")
- medications: any medicine, insulin, or supplement mentioned (e.g. "metformin", "insulin", "lantus")
- diet_habit: what they eat regularly (e.g. "eats outside daily", "skips breakfast")
- activity_level: exercise or movement habits
- sleep_pattern: sleep hours and quality
- stress_level: stress triggers or intensity
- main_goal: what they want to achieve
- food_preference: veg/non-veg, likes/dislikes
- lifestyle: work schedule, daily routine
- challenges: what makes health management hard for them

User message: "{$message}"

Rules:
- Extract only what is explicitly mentioned
- Values must be short, factual phrases (max 15 words)
- Return ONLY valid JSON like: {"health_condition": "Type 1 diabetes", "medications": "insulin injections daily"}
- If nothing relevant, return: {}
- No explanation, no extra text
PROMPT;
    }

    private function buildBulkExtractionPrompt(string $conversation, string $keys): string
    {
        return <<<PROMPT
You are a memory extraction system for a health coach AI.

Read this full consultation conversation and extract all important user facts.

Valid memory keys: {$keys}

Conversation:
{$conversation}

Rules:
- Extract only what the USER explicitly said (not the coach)
- Values must be short, factual phrases (max 20 words each)
- If user corrected themselves, use the latest version
- Return ONLY valid JSON like:
  {
    "health_condition": "Type 2 diabetes",
    "diet_habit": "eats outside daily, mostly rice and dal",
    "activity_level": "walks 20 minutes occasionally",
    "sleep_pattern": "6 hours, wakes up tired",
    "stress_level": "high, work pressure",
    "main_goal": "control blood sugar and lose weight",
    "food_preference": "vegetarian",
    "lifestyle": "desk job, very busy",
    "challenges": "no time to cook, eats late at night"
  }
- Only include keys where you found clear information
- No explanation, no extra text
PROMPT;
    }

    private function parseJson(string $raw): array
    {
        $clean = preg_replace('/```json|```/', '', $raw);
        $clean = trim($clean);

        $start = strpos($clean, '{');
        $end   = strrpos($clean, '}');

        if ($start === false || $end === false) return [];

        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }
}
