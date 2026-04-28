<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\UserMemory;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use App\Services\AI\WelcomeConsultationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class GenerateFitnessPlan implements ShouldQueue
{
    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        public User   $user,
        public int    $sessionId,
        public string $lang = 'en'
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf, WelcomeConsultationService $welcomeService): void
    {
        $user    = User::with(['goals'])->findOrFail($this->user->id);
        $isHindi = str_starts_with($this->lang, 'hi') || $this->lang === 'hi-roman';
        $fitnessPlanStored = false;

        try {
            $goals      = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
            $userMemory = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();

            $condition     = $userMemory['health_condition'] ?? $this->deriveConditionFromGoals($user);
            $stage         = $userMemory['current_stage']    ?? '';
            $activityLevel = $userMemory['activity_level']   ?? $user->activity_level ?? 'sedentary';
            $challenges    = $userMemory['challenges']        ?? '';
            $lifestyle     = $userMemory['lifestyle']         ?? '';
            $medications   = $userMemory['medications']       ?? '';
            $adherence     = $userMemory['adherence_pattern'] ?? '';

            $memoryLines = collect($userMemory)
                ->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)
                ->join("\n");

            $langNote = $isHindi
                ? 'Write all exercise names, descriptions, tips, and safety notes in Hindi (Devanagari script). Use English only for exercise terms with no Hindi equivalent.'
                : 'Write in English. Make it realistic and achievable for Indian lifestyle.';

            $stageNote     = $stage      ? "Current stage: {$stage}."         : '';
            $challengeNote = $challenges ? "Known challenges: {$challenges}."  : '';
            $lifestyleNote = $lifestyle  ? "Lifestyle: {$lifestyle}."          : '';
            $adherNote     = $adherence  ? "Adherence pattern: {$adherence}."  : '';
            $medNote       = $medications ? "Current medications: {$medications}." : '';

            $conditionGuidance = $this->getConditionFitnessGuidance($condition, $stage, $activityLevel);
            $difficultyLevel   = $this->resolveDifficultyLevel($activityLevel, $adherence);

            $prompt = <<<PROMPT
You are Rakhi, a senior Indian fitness coach and physiotherapist with 15 years of experience.
Create a deeply personalized, condition-safe 4-week progressive fitness plan for this patient.

PATIENT PROFILE:
Name: {$user->first_name} | Age: {$user->getAge()} yrs | Gender: {$user->gender}
Weight: {$user->weight} kg | Height: {$user->height} cm
Primary condition: {$condition} | Goals: {$goals}
Current activity level: {$activityLevel} | Difficulty level to use: {$difficultyLevel}
{$stageNote} {$challengeNote} {$lifestyleNote} {$adherNote} {$medNote}

FULL HEALTH MEMORY:
{$memoryLines}

CONDITION-SPECIFIC FITNESS GUIDANCE:
{$conditionGuidance}

{$langNote}

INSTRUCTIONS:
- All exercises must be safe and appropriate for: {$condition}.
- Start at difficulty level: {$difficultyLevel} — do not overwhelm the patient.
- If adherence is struggling, keep Week 1 very simple (just walking is fine).
- Each week must progressively build on the previous week.
- Include rest days — recovery is part of the plan.
- Safety notes must be specific to their condition and stage.
- Duration must be realistic for their lifestyle.

Return ONLY valid JSON in this exact structure:
{
  "overview": {
    "difficulty": "beginner|intermediate|advanced",
    "primary_activity": "main type of exercise",
    "weekly_commitment": "total minutes per week",
    "goal_of_plan": "what this plan aims to achieve for their condition"
  },
  "weeks": [
    {
      "week": 1,
      "focus": "week theme/focus",
      "days": [
        {
          "day": "Monday",
          "activity_type": "cardio|strength|yoga|rest|active_rest",
          "description": "what to do",
          "exercises": ["exercise 1 with reps/duration"],
          "duration": 30,
          "intensity": "low|moderate|high",
          "safety_note": "any safety note (optional)"
        }
      ]
    }
  ],
  "tips": ["practical tip 1", "practical tip 2"],
  "safety_precautions": ["condition-specific safety rule 1"],
  "when_to_stop": ["stop if you experience symptom 1"]
}
Return ONLY JSON. No extra text.
PROMPT;

            $response = $llm->chat($prompt);
            $planData = $this->parseJson($response) ?? $this->fallbackFitnessPlan($condition, $difficultyLevel);

            $version = UserPlan::nextVersion($user->id, 'fitness');
            $fileUrl = $pdf->generateFitnessPlan($user, $planData, $this->lang);

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'fitness',
                'coach_id'     => $user->primaryCoach()?->id ?? 1,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'language'     => $this->lang,
                'plan_data'    => $planData,
                'version'      => $version,
                'generated_at' => now(),
            ]);

            $fitnessPlanStored = true;
            Log::info("Fitness plan v{$version} stored for user {$user->id}");

        } catch (\Exception $e) {
            Log::error('GenerateFitnessPlan failed for user ' . $this->user->id . ': ' . $e->getMessage(), [
                'user_id'    => $this->user->id,
                'session_id' => $this->sessionId,
            ]);
        }

        // ── Final step: always runs — set active_coaching regardless of fitness plan success ──
        $user->refresh();
        $finalState = ($user->plan_generation_state === 'failed' || !$fitnessPlanStored)
            ? 'failed'
            : 'completed';

        $user->update([
            'first_consultation_complete' => true,
            'consultation_state'          => 'active_coaching',
            'plan_generation_state'       => $finalState,
        ]);

        ChatSession::where('id', $this->sessionId)
            ->update(['is_first_consultation' => false]);

        // ── Resolve the chat session to post PDF links to ─────────────────────
        // If the consultation happened via voice, $this->sessionId is a voice session.
        // PDF links must always go to the parent chat session so they appear in the chat UI.
        $targetSessionId = $this->sessionId;
        $voiceSession    = ChatSession::where('id', $this->sessionId)
            ->where('session_type', 'voice')
            ->first();

        if ($voiceSession && $voiceSession->parent_chat_session_id) {
            $targetSessionId = $voiceSession->parent_chat_session_id;
            // Also mark the parent chat session as no longer in first consultation
            ChatSession::where('id', $targetSessionId)
                ->update(['is_first_consultation' => false]);
        }

        // ── Fetch all 3 plan URLs for combined delivery ────────────────────────────────
        $consultationPlan = UserPlan::latestForUser($user->id, 'consultation');
        $dietPlan         = UserPlan::latestForUser($user->id, 'diet');
        $fitnessPlan      = UserPlan::latestForUser($user->id, 'fitness');

        // ── Handover message + individual PDF links ───────────────────────────
        if ($finalState === 'completed') {
            ChatMessage::create([
                'session_id'   => $targetSessionId,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $welcomeService->getHandoverMessage($user, $this->lang),
                'message_type' => 'text',
            ]);
        }

        foreach ([
            ['plan' => $consultationPlan, 'label' => 'Health Consultation Report'],
            ['plan' => $dietPlan,         'label' => 'Diet Plan'],
            ['plan' => $fitnessPlan,      'label' => 'Fitness Plan'],
        ] as $item) {
            if ($item['plan']) {
                ChatMessage::create([
                    'session_id'   => $targetSessionId,
                    'user_id'      => $user->id,
                    'role'         => 'rakhi',
                    'message'      => $item['label'],
                    'message_type' => 'pdf',
                    'file_url'     => $item['plan']->file_url,
                ]);
            }
        }

        if ($finalState === 'failed') {
            $failMsg = $isHindi
                ? "Kuch plans generate karte waqt problem aayi. Aap Plans screen se 'Regenerate' kar sakte hain. 🙏"
                : "Some plans could not be generated. You can retry from the Plans screen. 🙏";
            ChatMessage::create([
                'session_id'   => $targetSessionId,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $failMsg,
                'message_type' => 'text',
            ]);
        }

        Log::info('Consultation complete', [
            'user_id'        => $user->id,
            'plan_state'     => $finalState,
            'target_session' => $targetSessionId,
        ]);
    }

    // ─────────────────────────────────────────────
    // CONDITION-SPECIFIC FITNESS GUIDANCE
    // ─────────────────────────────────────────────

    private function getConditionFitnessGuidance(string $condition, string $stage, string $activityLevel): string
    {
        $lower = strtolower($condition);

        if (str_contains($lower, 'pregnan')) {
            $trimester = strtolower($stage);
            $base = 'PREGNANCY SAFETY: No high-impact exercise, no lying flat on back after first trimester, no heavy lifting, no contact sports. Safe: walking, prenatal yoga, swimming, light stretching. Always advise to check with their doctor first.';
            if (str_contains($trimester, 'third')) return $base . ' Third trimester: keep sessions short (15-20 min), focus on breathing and gentle stretching.';
            return $base;
        }
        if (str_contains($lower, 'diabet')) {
            return 'DIABETES SAFETY: Check blood sugar before exercise — if below 100 mg/dL, have a small snack first. Carry fast sugar during exercise. 30-min walk after meals is highly effective. Strength training 2-3x/week improves insulin sensitivity. Avoid intense exercise if sugar is above 250 mg/dL.';
        }
        if (str_contains($lower, 'pcos')) {
            return 'PCOS FITNESS: Strength training 3x/week is most effective for insulin resistance. Moderate cardio 2-3x/week. AVOID excessive cardio (>60 min daily) — raises cortisol. Yoga is excellent. Rest days are important.';
        }
        if (str_contains($lower, 'thyroid')) {
            $hypo = str_contains(strtolower($stage), 'hypo') || str_contains($lower, 'hypo');
            if ($hypo) return 'HYPOTHYROID FITNESS: Start slow — fatigue is real. Even 20-30 min walk daily is beneficial. Strength training helps counter slow metabolism. Do NOT push through extreme fatigue.';
            return 'HYPERTHYROID FITNESS: Avoid high intensity until levels are controlled. Walking and gentle yoga are safe. No HIIT until TSH is stable.';
        }
        if (str_contains($lower, 'weight')) {
            return 'WEIGHT LOSS FITNESS: Combination of strength training (3x/week) + cardio (150 min/week) is optimal. Walking after meals burns extra calories. Consistency beats intensity.';
        }
        if (str_contains($lower, 'postpartum')) {
            return 'POSTPARTUM SAFETY: No intense exercise for at least 6 weeks post-delivery (12 weeks for C-section). Start with pelvic floor exercises and gentle walking. No crunches until core is assessed.';
        }

        return 'General fitness: combination of walking, yoga, and light strength training. Start with 20-30 min sessions, 4-5 days/week. Build gradually. Rest days are important.';
    }

    private function resolveDifficultyLevel(string $activityLevel, string $adherence): string
    {
        $lower = strtolower($activityLevel);
        if (str_contains($lower, 'sedentary') || str_contains($lower, 'very low') || str_contains($lower, 'no exercise')) {
            return 'beginner';
        }
        if (str_contains($lower, 'high') || str_contains($lower, 'very active') || str_contains($lower, 'athlete')) {
            return 'advanced';
        }
        if (strtolower($adherence) === 'struggling') return 'beginner';
        return 'intermediate';
    }

    private function deriveConditionFromGoals(User $user): string
    {
        $slug = strtolower($user->goals->first()?->slug ?? '');
        return match(true) {
            str_contains($slug, 'diabet')   => 'Diabetes',
            str_contains($slug, 'pcos')     => 'PCOS',
            str_contains($slug, 'thyroid')  => 'Thyroid',
            str_contains($slug, 'pregnan')  => 'Pregnancy',
            str_contains($slug, 'weight')   => 'Weight Management',
            str_contains($slug, 'postpart') => 'Postpartum Recovery',
            default                         => 'General Wellness',
        };
    }

    private function fallbackFitnessPlan(string $condition, string $difficulty): array
    {
        $isPregnancy = str_contains(strtolower($condition), 'pregnan');
        return [
            'overview' => [
                'difficulty'        => $difficulty,
                'primary_activity'  => $isPregnancy ? 'Prenatal walking + gentle yoga' : 'Walking + bodyweight exercises',
                'weekly_commitment' => $isPregnancy ? '100-120 minutes' : '150 minutes',
                'goal_of_plan'      => "Support {$condition} management through safe, progressive movement",
            ],
            'weeks' => [
                ['week' => 1, 'focus' => 'Building the habit — start gentle', 'days' => [
                    ['day' => 'Monday',    'activity_type' => 'cardio',      'description' => 'Morning walk',      'exercises' => ['20 min brisk walk'],                              'duration' => 20, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Tuesday',   'activity_type' => 'yoga',        'description' => 'Gentle stretching', 'exercises' => ['10 min stretching', '10 min basic yoga'],        'duration' => 20, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Wednesday', 'activity_type' => 'rest',        'description' => 'Rest day',          'exercises' => ['Rest and recover'],                               'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Thursday',  'activity_type' => 'cardio',      'description' => 'Morning walk',      'exercises' => ['25 min brisk walk'],                              'duration' => 25, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Friday',    'activity_type' => 'strength',    'description' => 'Bodyweight basics', 'exercises' => ['10 squats', '10 wall push-ups', '20 sec plank'], 'duration' => 20, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Saturday',  'activity_type' => 'active_rest', 'description' => 'Light activity',    'exercises' => ['30 min leisure walk'],                            'duration' => 30, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Sunday',    'activity_type' => 'rest',        'description' => 'Full rest',         'exercises' => ['Rest'],                                           'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                ]],
                ['week' => 2, 'focus' => 'Building consistency', 'days' => [
                    ['day' => 'Monday',    'activity_type' => 'cardio',      'description' => 'Walk + squats',     'exercises' => ['25 min walk', '15 squats'],                       'duration' => 30, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Tuesday',   'activity_type' => 'yoga',        'description' => 'Yoga flow',         'exercises' => ['20 min yoga'],                                    'duration' => 20, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Wednesday', 'activity_type' => 'rest',        'description' => 'Rest',              'exercises' => ['Rest'],                                           'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Thursday',  'activity_type' => 'cardio',      'description' => 'Cardio walk',       'exercises' => ['30 min brisk walk'],                              'duration' => 30, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Friday',    'activity_type' => 'strength',    'description' => 'Strength basics',   'exercises' => ['15 squats', '10 push-ups', '30 sec plank'],      'duration' => 25, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Saturday',  'activity_type' => 'active_rest', 'description' => 'Active day',        'exercises' => ['Cycling or swimming 30 min'],                    'duration' => 30, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Sunday',    'activity_type' => 'rest',        'description' => 'Rest',              'exercises' => ['Rest'],                                           'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                ]],
                ['week' => 3, 'focus' => 'Increasing intensity', 'days' => [
                    ['day' => 'Monday',    'activity_type' => 'cardio',      'description' => 'Cardio + core',     'exercises' => ['30 min walk/jog', '20 crunches'],                 'duration' => 35, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Tuesday',   'activity_type' => 'yoga',        'description' => 'Yoga + stretching', 'exercises' => ['25 min yoga'],                                    'duration' => 25, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Wednesday', 'activity_type' => 'rest',        'description' => 'Rest',              'exercises' => ['Rest'],                                           'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Thursday',  'activity_type' => 'strength',    'description' => 'Full body workout', 'exercises' => ['20 squats', '15 push-ups', '1 min plank'],       'duration' => 30, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Friday',    'activity_type' => 'cardio',      'description' => 'Cardio',            'exercises' => ['35 min brisk walk or jog'],                       'duration' => 35, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Saturday',  'activity_type' => 'active_rest', 'description' => 'Active rest',       'exercises' => ['Outdoor activity of choice'],                    'duration' => 30, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Sunday',    'activity_type' => 'rest',        'description' => 'Rest',              'exercises' => ['Rest'],                                           'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                ]],
                ['week' => 4, 'focus' => 'Maintaining and progressing', 'days' => [
                    ['day' => 'Monday',    'activity_type' => 'cardio',      'description' => 'Cardio',            'exercises' => ['40 min walk/jog'],                                'duration' => 40, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Tuesday',   'activity_type' => 'strength',    'description' => 'Strength',          'exercises' => ['25 squats', '20 push-ups', '1.5 min plank'],     'duration' => 30, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Wednesday', 'activity_type' => 'yoga',        'description' => 'Yoga',              'exercises' => ['30 min yoga'],                                    'duration' => 30, 'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Thursday',  'activity_type' => 'rest',        'description' => 'Rest',              'exercises' => ['Rest'],                                           'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                    ['day' => 'Friday',    'activity_type' => 'strength',    'description' => 'Full body',         'exercises' => ['30 squats', '20 push-ups', '20 crunches'],       'duration' => 35, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Saturday',  'activity_type' => 'cardio',      'description' => 'Long walk',         'exercises' => ['45 min outdoor walk'],                            'duration' => 45, 'intensity' => 'moderate', 'safety_note' => ''],
                    ['day' => 'Sunday',    'activity_type' => 'rest',        'description' => 'Rest',              'exercises' => ['Rest and recover'],                               'duration' => 0,  'intensity' => 'low',      'safety_note' => ''],
                ]],
            ],
            'tips' => [
                'Consistency matters more than intensity — showing up every day beats one hard session',
                'Drink water before and after every workout',
                'Warm up for 5 minutes before any exercise session',
                'Listen to your body — rest when you genuinely need it',
            ],
            'safety_precautions' => [
                "All exercises are selected to be safe for {$condition}",
                'Stop immediately if you feel pain, dizziness, or shortness of breath',
                'Consult your doctor before starting if you have any recent injury or surgery',
            ],
            'when_to_stop' => [
                'Chest pain or tightness',
                'Severe dizziness or feeling faint',
                'Unusual pain or swelling',
                'Difficulty breathing',
            ],
        ];
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
