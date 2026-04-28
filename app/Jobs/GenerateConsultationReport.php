<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserMemory;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class GenerateConsultationReport implements ShouldQueue
{
    public int $tries   = 2;
    public int $timeout = 300; // 5 min — LLM calls can be slow

    public function __construct(
        public User   $user,
        public int    $sessionId,
        public string $lang = 'en'
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        try {
            $user       = User::with(['goals', 'coaches'])->findOrFail($this->user->id);
            $isHindi    = str_starts_with($this->lang, 'hi') || $this->lang === 'hi-roman';
            $goals      = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
            $userMemory = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();

            // ── Build rich context ────────────────────────────────────────────
            $condition    = $userMemory['health_condition'] ?? $this->deriveConditionFromGoals($user);
            $stage        = $userMemory['current_stage']    ?? '';
            $medications  = $userMemory['medications']      ?? '';
            $challenges   = $userMemory['challenges']       ?? '';
            $lifestyle    = $userMemory['lifestyle']        ?? '';
            $emotionState = $userMemory['emotional_state']  ?? '';
            $adherence    = $userMemory['adherence_pattern'] ?? '';

            $memoryLines = collect($userMemory)
                ->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)
                ->join("\n");

            // Resolve all session IDs in the consultation chain (voice + chat)
            $sessionIds = [$this->sessionId];
            $thisSession = ChatSession::find($this->sessionId);
            if ($thisSession?->parent_chat_session_id) {
                $sessionIds[] = $thisSession->parent_chat_session_id;
                $siblingIds = ChatSession::where('parent_chat_session_id', $thisSession->parent_chat_session_id)
                    ->where('user_id', $user->id)->pluck('id')->toArray();
                $sessionIds = array_unique(array_merge($sessionIds, $siblingIds));
            }

            $conversation = ChatMessage::whereIn('session_id', $sessionIds)
                ->where('user_id', $user->id)
                ->orderBy('id')->get()
                ->map(fn($m) => ucfirst($m->role) . ': ' . $m->message)
                ->join("\n");

            $checkinsText = DailyCheckin::where('user_id', $user->id)
                ->latest()->take(7)->get()
                ->map(fn($c) => "Date: {$c->checkin_date}, Mood: {$c->mood}, Energy: {$c->energy_level}/10, Sleep: {$c->sleep_hours}h, Exercise: " . ($c->exercise_done ? 'Yes' : 'No'))
                ->join("\n") ?: 'No recent check-ins';

            $mealsText = MealLog::where('user_id', $user->id)
                ->latest()->take(10)->get()
                ->map(fn($m) => "{$m->meal_name} — {$m->calories} kcal, {$m->protein}g protein")
                ->join("\n") ?: 'No recent meals logged';

            $langNote = $isHindi
                ? 'Write all text in Hindi (Devanagari script). Use English only for medical terms with no Hindi equivalent.'
                : 'Write in English. Be empathetic, practical, and supportive in tone.';

            $stageNote  = $stage       ? "Current stage: {$stage}."        : '';
            $medNote    = $medications ? "Current medications: {$medications}." : '';
            $emotNote   = $emotionState ? "Emotional state: {$emotionState}." : '';
            $adherNote  = $adherence   ? "Adherence pattern: {$adherence}." : '';

            $prompt = <<<PROMPT
You are Rakhi, a senior Indian health coach and clinical nutritionist with 15 years of experience.
Generate a detailed, personalized consultation report for the following patient.

PATIENT PROFILE:
Name: {$user->first_name} {$user->last_name} | Age: {$user->getAge()} yrs | Gender: {$user->gender}
Weight: {$user->weight} kg | Height: {$user->height} cm
Primary condition: {$condition} | Goals: {$goals}
{$stageNote} {$medNote} {$emotNote} {$adherNote}

HEALTH MEMORY:
{$memoryLines}

CONSULTATION CONVERSATION:
{$conversation}

RECENT CHECK-INS (last 7 days):
{$checkinsText}

RECENT MEALS (last 10 logs):
{$mealsText}

{$langNote}

INSTRUCTIONS:
- This report must feel like it was written by a real doctor/coach who listened carefully.
- Every observation must reference the patient's actual data — no generic statements.
- Identify real patterns from check-ins and meals (e.g. low energy + poor sleep = fatigue pattern).
- Condition-specific risks must be relevant to: {$condition}.
- Focus areas must be actionable and prioritised by impact.
- Tone: warm, supportive, non-judgmental — like a caring doctor's letter.

Return ONLY valid JSON in this exact structure:
{
  "condition_summary": "2-3 sentence summary of the patient's primary condition and current state",
  "key_observations": [
    {"area": "area name", "observation": "specific observation based on their data", "severity": "low|medium|high"}
  ],
  "identified_risks": [
    {"risk": "risk description", "reason": "why this is a risk for this patient specifically"}
  ],
  "goals": [
    {"goal": "goal name", "current_status": "where they are now", "priority": "high|medium|low"}
  ],
  "focus_areas": [
    {"area": "focus area", "why": "why this matters for their condition", "action": "specific action to take"}
  ],
  "coach_note": "A warm, personal 2-3 sentence note from Rakhi to the patient — encouraging and specific to their situation",
  "precautions": ["condition-specific precaution 1", "precaution 2"]
}
Return ONLY JSON. No extra text.
PROMPT;

            $response = $llm->chat($prompt);
            $report   = $this->parseJson($response) ?? $this->fallbackReport($condition, $goals);

            // Sync memory → user profile fields
            $this->syncMemoryToProfile($user, $userMemory);

            $version = UserPlan::nextVersion($user->id, 'consultation');
            $fileUrl = $pdf->generateConsultationReport($user, $report, $userMemory, $this->lang);

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'consultation',
                'coach_id'     => $user->primaryCoach()?->id ?? 1,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'language'     => $this->lang,
                'plan_data'    => $report,
                'version'      => $version,
                'generated_at' => now(),
            ]);

            // No individual chat message here — all 3 PDFs delivered together at the end
            Log::info("Consultation report v{$version} stored for user {$user->id}");

            dispatch(new GenerateDietPlan($user, $this->sessionId, $this->lang));

        } catch (\Exception $e) {
            Log::error('GenerateConsultationReport failed for user ' . $this->user->id . ': ' . $e->getMessage(), [
                'user_id'    => $this->user->id,
                'session_id' => $this->sessionId,
                'exception'  => $e->getMessage(),
            ]);

            // Resolve target session: if voice consultation, post to parent chat session
            $targetSessionId = $this->sessionId;
            $voiceSession    = ChatSession::where('id', $this->sessionId)
                ->where('session_type', 'voice')->first();
            if ($voiceSession?->parent_chat_session_id) {
                $targetSessionId = $voiceSession->parent_chat_session_id;
            }

            $isHindi = str_starts_with($this->lang, 'hi') || $this->lang === 'hi-roman';
            $failMsg = $isHindi
                ? "Aapki report generate karte waqt ek chhoti si problem aayi. Main abhi Diet Plan aur Fitness Plan bana rahi hoon — woh aapko milenge. 🙏"
                : "There was a small issue generating your consultation report. I'm continuing with your Diet Plan and Fitness Plan — those will be delivered shortly. 🙏";

            ChatMessage::create([
                'session_id'   => $targetSessionId,
                'user_id'      => $this->user->id,
                'role'         => 'rakhi',
                'message'      => $failMsg,
                'message_type' => 'text',
            ]);

            // Still chain diet plan so user gets remaining documents
            dispatch(new GenerateDietPlan($this->user, $this->sessionId, $this->lang));
        }
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    private function deriveConditionFromGoals(User $user): string
    {
        $slug = strtolower($user->goals->first()?->slug ?? '');
        return match(true) {
            str_contains($slug, 'diabet')  => 'Diabetes',
            str_contains($slug, 'pcos')    => 'PCOS',
            str_contains($slug, 'thyroid') => 'Thyroid',
            str_contains($slug, 'pregnan') => 'Pregnancy',
            str_contains($slug, 'weight')  => 'Weight Management',
            str_contains($slug, 'stress')  => 'Stress Management',
            str_contains($slug, 'sleep')   => 'Sleep Issues',
            str_contains($slug, 'energy')  => 'Low Energy / Fatigue',
            str_contains($slug, 'mental')  => 'Mental Wellness',
            default                        => 'General Wellness',
        };
    }

    private function fallbackReport(string $condition, string $goals): array
    {
        return [
            'condition_summary' => "Based on our consultation, your primary focus is {$condition}. Your goals include {$goals}. A personalized plan has been prepared to support your journey.",
            'key_observations'  => [
                ['area' => 'Health Goals',    'observation' => "Working towards: {$goals}",                                    'severity' => 'low'],
                ['area' => 'Lifestyle',       'observation' => 'Lifestyle and habit data collected during consultation.',       'severity' => 'low'],
                ['area' => 'Nutrition',       'observation' => 'Dietary patterns reviewed and personalized plan prepared.',    'severity' => 'low'],
            ],
            'identified_risks'  => [
                ['risk' => 'Inconsistent routine', 'reason' => 'Building consistent habits is key for managing ' . $condition],
            ],
            'goals'             => [
                ['goal' => $goals, 'current_status' => 'Starting phase', 'priority' => 'high'],
            ],
            'focus_areas'       => [
                ['area' => 'Diet',     'why' => 'Nutrition is foundational for ' . $condition, 'action' => 'Follow the personalized diet plan provided'],
                ['area' => 'Movement', 'why' => 'Regular activity supports overall health',    'action' => 'Follow the personalized fitness plan provided'],
            ],
            'coach_note'        => "I've listened carefully to everything you've shared and prepared this plan specifically for you. Take it one step at a time — small, consistent changes make the biggest difference. I'm here with you every step of the way. 🌸",
            'precautions'       => ['Follow your plan consistently for best results', 'Consult your doctor for any medication-related questions'],
        ];
    }

    private function syncMemoryToProfile(User $user, array $userMemory): void
    {
        $syncData = [];
        $fieldMap = [
            'diet_habit'     => 'diet_preference',
            'activity_level' => 'activity_level',
            'sleep_pattern'  => 'sleep_hours',
            'stress_level'   => 'stress_level',
        ];
        foreach ($fieldMap as $memKey => $userField) {
            if (!empty($userMemory[$memKey])) {
                $val = $userMemory[$memKey];
                if ($userField === 'sleep_hours') {
                    preg_match('/\d+(\.\d+)?/', $val, $m);
                    $val = $m[0] ?? $val;
                }
                $syncData[$userField] = $val;
            }
        }
        if (!empty($syncData)) {
            $user->update($syncData);
            $user->refresh();
        }
    }

    private function parseJson(string $raw): ?array
    {
        $clean = preg_replace('/^```[a-z]*\s*/m', '', $raw);
        $clean = preg_replace('/^```\s*$/m', '', $clean);
        $clean = trim($clean);
        $start = strpos($clean, '{');
        $end   = strrpos($clean, '}');
        if ($start === false || $end === false) return null;
        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }
}
