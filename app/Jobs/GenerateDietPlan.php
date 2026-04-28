<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserMemory;
use App\Models\UserPlan;
use App\Services\AI\LLMRouter;
use App\Services\PDF\PDFService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class GenerateDietPlan implements ShouldQueue
{
    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        public User   $user,
        public int    $sessionId,
        public string $lang = 'en'
    ) {}

    public function handle(LLMRouter $llm, PDFService $pdf): void
    {
        try {
            $user    = User::with(['goals', 'language'])->findOrFail($this->user->id);
            $isHindi = str_starts_with($this->lang, 'hi') || $this->lang === 'hi-roman';
            $goals   = $user->goals->pluck('name')->join(', ') ?: 'general wellness';

            $userMemory = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();

            // ── Deep personalization context ──────────────────────────────────
            $condition    = $userMemory['health_condition'] ?? $this->deriveConditionFromGoals($user);
            $stage        = $userMemory['current_stage']    ?? '';
            $dietHabit    = $userMemory['diet_habit']       ?? '';
            $foodPref     = $userMemory['food_preference']  ?? $user->diet_preference ?? '';
            $medications  = $userMemory['medications']      ?? '';
            $challenges   = $userMemory['challenges']       ?? '';
            $lifestyle    = $userMemory['lifestyle']        ?? '';
            $activityLevel= $userMemory['activity_level']  ?? $user->activity_level ?? 'moderate';
            $emotionState = $userMemory['emotional_state']  ?? '';
            $adherence    = $userMemory['adherence_pattern'] ?? '';

            $memoryLines = collect($userMemory)
                ->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)
                ->join("\n");

            $langNote = $isHindi
                ? 'Write all meal names, descriptions, tips, and alternatives in Hindi (Devanagari script). Use English only for medical/nutritional terms.'
                : 'Write in English. Use Indian food options. Be realistic and practical for Indian lifestyle.';

            $stageNote    = $stage        ? "Current stage: {$stage}."                  : '';
            $habitNote    = $dietHabit    ? "Known diet habit: {$dietHabit}."            : '';
            $challengeNote= $challenges   ? "Known challenges: {$challenges}."           : '';
            $lifestyleNote= $lifestyle    ? "Lifestyle: {$lifestyle}."                   : '';
            $emotNote     = $emotionState ? "Emotional state: {$emotionState}."          : '';
            $adherNote    = $adherence    ? "Adherence pattern: {$adherence}."           : '';
            $medNote      = $medications  ? "Current medications: {$medications}."       : '';

            $conditionGuidance = $this->getConditionDietGuidance($condition, $stage);

            $prompt = <<<PROMPT
You are Rakhi, a senior Indian clinical nutritionist with 15 years of experience.
Create a deeply personalized, condition-safe daily diet plan for this patient.

PATIENT PROFILE:
Name: {$user->first_name} | Age: {$user->getAge()} yrs | Gender: {$user->gender}
Weight: {$user->weight} kg | Height: {$user->height} cm
Primary condition: {$condition} | Goals: {$goals}
Diet preference: {$foodPref} | Activity level: {$activityLevel}
{$stageNote} {$habitNote} {$challengeNote} {$lifestyleNote} {$emotNote} {$adherNote} {$medNote}

FULL HEALTH MEMORY:
{$memoryLines}

CONDITION-SPECIFIC GUIDANCE:
{$conditionGuidance}

{$langNote}

INSTRUCTIONS:
- Every meal must be safe and appropriate for: {$condition}.
- Use Indian foods the patient actually eats — reference their diet habit and food preference.
- Timing must be realistic for their lifestyle.
- Alternatives must be practical (e.g. if they can't cook, suggest ready options).
- Precautions must be specific to their condition and medications.
- If adherence is struggling, keep the plan simple — do not overwhelm.
- Calorie targets must match their weight, height, age, gender, and activity level.

Return ONLY valid JSON in this exact structure:
{
  "daily_targets": {
    "calories": 0,
    "protein": 0,
    "carbs": 0,
    "fat": 0,
    "water_litres": 0,
    "fibre_g": 0
  },
  "meals": [
    {
      "time": "Early Morning",
      "timing_note": "when to have this (e.g. 6:30-7:00 AM, on empty stomach)",
      "name": "meal name",
      "description": "what exactly to eat and how much",
      "calories": 0,
      "protein_g": 0,
      "alternatives": ["alternative option 1", "alternative option 2"],
      "condition_note": "why this meal is good for their condition (optional)"
    }
  ],
  "foods_to_avoid": [
    {"food": "food name", "reason": "why to avoid for their condition"}
  ],
  "tips": ["practical tip 1", "practical tip 2", "practical tip 3"],
  "precautions": ["condition-specific precaution 1", "precaution 2"]
}
Return ONLY JSON. No extra text.
PROMPT;

            $response = $llm->chat($prompt);
            $planData = $this->parseJson($response) ?? $this->fallbackDietPlan($user, $condition);

            $version = UserPlan::nextVersion($user->id, 'diet');
            $fileUrl = $pdf->generateDietPlan($user, $planData, $this->lang);

            UserPlan::create([
                'user_id'      => $user->id,
                'plan_type'    => 'diet',
                'coach_id'     => $user->primaryCoach()?->id ?? 1,
                'session_id'   => $this->sessionId,
                'file_url'     => $fileUrl,
                'language'     => $this->lang,
                'plan_data'    => $planData,
                'version'      => $version,
                'generated_at' => now(),
            ]);

            Log::info("Diet plan v{$version} stored for user {$user->id}");

            dispatch(new GenerateFitnessPlan($user, $this->sessionId, $this->lang));

        } catch (\Exception $e) {
            Log::error('GenerateDietPlan failed for user ' . $this->user->id . ': ' . $e->getMessage(), [
                'user_id'    => $this->user->id,
                'session_id' => $this->sessionId,
            ]);
            // Do NOT mark state as failed here — let GenerateFitnessPlan do the final state update
            dispatch(new GenerateFitnessPlan($this->user, $this->sessionId, $this->lang));
        }
    }

    // ─────────────────────────────────────────────
    // CONDITION-SPECIFIC DIET GUIDANCE
    // ─────────────────────────────────────────────

    private function getConditionDietGuidance(string $condition, string $stage): string
    {
        $lower = strtolower($condition);

        if (str_contains($lower, 'diabet')) {
            return 'Low GI diet is essential. Avoid refined carbs, sugar, white rice in large portions, fruit juice. Include: whole grains (oats, millets, brown rice), dal, vegetables, protein at every meal. Small frequent meals every 3-4 hours. Post-meal walk of 10-15 min. Gestational diabetes: stricter targets, no meal skipping.';
        }
        if (str_contains($lower, 'pcos')) {
            return 'Anti-inflammatory, low GI diet. Avoid: sugar, refined carbs, dairy excess, processed food. Include: millets, dal, vegetables, seeds (flaxseed, pumpkin), spearmint tea. Protein at every meal reduces insulin resistance. Avoid skipping meals — blood sugar stability is key.';
        }
        if (str_contains($lower, 'thyroid')) {
            $hypo = str_contains(strtolower($stage), 'hypo') || str_contains($lower, 'hypo');
            if ($hypo) {
                return 'Hypothyroid diet: avoid raw cruciferous vegetables in large amounts (cook them). Include selenium-rich foods (sunflower seeds, eggs). Iodised salt is sufficient. Medication timing: take on empty stomach 30-60 min before food. Avoid calcium, iron, coffee within 4 hours of medication.';
            }
            return 'Thyroid diet: avoid excess iodine supplements. Include calcium-rich foods. Anti-inflammatory focus. Selenium-rich foods support thyroid function.';
        }
        if (str_contains($lower, 'pregnan')) {
            $trimester = strtolower($stage);
            $base = 'Pregnancy nutrition: folic acid (leafy greens, lentils), iron (spinach, dates, jaggery), calcium (dairy, ragi), protein (dal, eggs, paneer), omega-3 (walnuts, flaxseed). AVOID: raw papaya, pineapple excess, raw eggs, unpasteurised dairy, alcohol, high-mercury fish. No meal skipping — baby needs consistent nutrition.';
            if (str_contains($trimester, 'first'))  return $base . ' First trimester: focus on folic acid, manage nausea with small frequent meals, ginger tea.';
            if (str_contains($trimester, 'second')) return $base . ' Second trimester: increase iron and calcium. Healthy weight gain ~0.5 kg/week.';
            if (str_contains($trimester, 'third'))  return $base . ' Third trimester: smaller more frequent meals (stomach compressed). Increase fibre to prevent constipation.';
            return $base;
        }
        if (str_contains($lower, 'weight')) {
            return 'Weight loss diet: calorie deficit of 300-500 kcal/day. High protein (1.2-1.6g/kg) preserves muscle. High fibre keeps full. Reduce refined carbs — do NOT eliminate. Avoid: maida, sugar, fried food, packaged snacks, fruit juice. Include: dal, sabzi, whole grains, curd, eggs/chicken. Eat every 3-4 hours to prevent bingeing.';
        }
        if (str_contains($lower, 'postpartum')) {
            return 'Postpartum nutrition: high protein for recovery and breastfeeding. Iron-rich foods (anaemia is common post-delivery). Calcium for bone recovery. Hydration is critical if breastfeeding. Avoid crash dieting — body needs nutrients for recovery. Galactagogues if breastfeeding: methi, jeera, dill, oats.';
        }

        return 'Balanced Indian diet: dal, sabzi, roti/rice (moderate), curd, seasonal fruits and vegetables. Adequate protein at every meal. Limit sugar, fried food, and packaged items. Drink 8-10 glasses of water daily.';
    }

    private function deriveConditionFromGoals(User $user): string
    {
        $slug = strtolower($user->goals->first()?->slug ?? '');
        return match(true) {
            str_contains($slug, 'diabet')  => 'Diabetes',
            str_contains($slug, 'pcos')    => 'PCOS',
            str_contains($slug, 'thyroid') => 'Thyroid',
            str_contains($slug, 'pregnan') => 'Pregnancy',
            str_contains($slug, 'weight')  => 'Weight Management',
            str_contains($slug, 'postpart')=> 'Postpartum Recovery',
            default                        => 'General Wellness',
        };
    }

    private function fallbackDietPlan(User $user, string $condition): array
    {
        $calories = match(strtolower($user->activity_level ?? '')) {
            'high', 'very active' => 2200,
            'moderate', 'medium'  => 1900,
            default               => 1600,
        };
        return [
            'daily_targets' => [
                'calories'     => $calories,
                'protein'      => 80,
                'carbs'        => 220,
                'fat'          => 55,
                'water_litres' => 2.5,
                'fibre_g'      => 25,
            ],
            'meals' => [
                ['time' => 'Early Morning', 'timing_note' => '6:30–7:00 AM, on empty stomach', 'name' => 'Warm water + soaked almonds',       'description' => '1 glass warm water with lemon, 5 soaked almonds',                    'calories' => 50,  'protein_g' => 2,  'alternatives' => ['Jeera water', 'Methi water'],                    'condition_note' => ''],
                ['time' => 'Breakfast',     'timing_note' => '8:00–9:00 AM',                   'name' => 'Poha / Upma with vegetables',       'description' => '1.5 cups poha with peas, carrots, and a side of curd',               'calories' => 320, 'protein_g' => 10, 'alternatives' => ['Vegetable oats', 'Moong dal chilla'],            'condition_note' => ''],
                ['time' => 'Mid Morning',   'timing_note' => '11:00 AM',                        'name' => 'Seasonal fruit',                    'description' => '1 apple or pear or guava',                                           'calories' => 80,  'protein_g' => 1,  'alternatives' => ['Handful of roasted chana', 'Small bowl of sprouts'], 'condition_note' => ''],
                ['time' => 'Lunch',         'timing_note' => '1:00–2:00 PM',                   'name' => 'Dal + Roti + Sabzi + Salad',        'description' => '2 rotis, 1 bowl dal, 1 bowl sabzi, cucumber-tomato salad',           'calories' => 500, 'protein_g' => 18, 'alternatives' => ['Brown rice + dal + sabzi', 'Khichdi with curd'],  'condition_note' => ''],
                ['time' => 'Evening',       'timing_note' => '4:30–5:00 PM',                   'name' => 'Light snack + chai',                'description' => '1 cup low-sugar chai, roasted makhana or chana',                    'calories' => 150, 'protein_g' => 5,  'alternatives' => ['Buttermilk + handful of nuts', 'Fruit + green tea'], 'condition_note' => ''],
                ['time' => 'Dinner',        'timing_note' => '7:30–8:30 PM (2 hrs before bed)','name' => 'Light Dal + Roti + Sabzi',          'description' => '1-2 rotis, 1 bowl dal, 1 bowl sabzi — lighter than lunch',          'calories' => 420, 'protein_g' => 15, 'alternatives' => ['Vegetable soup + 1 roti', 'Khichdi'],             'condition_note' => ''],
            ],
            'foods_to_avoid' => [
                ['food' => 'Sugary drinks and packaged juices', 'reason' => 'High sugar, no fibre — causes blood sugar spikes'],
                ['food' => 'Maida-based foods (white bread, biscuits, naan)', 'reason' => 'Refined carbs with no nutritional value'],
                ['food' => 'Fried snacks (samosa, chips, pakoda)', 'reason' => 'High in unhealthy fats and calories'],
            ],
            'tips' => [
                'Eat dinner at least 2 hours before sleeping',
                'Drink 8-10 glasses of water daily — start with 1 glass before each meal',
                'A 10-15 minute walk after lunch and dinner significantly helps digestion and blood sugar',
            ],
            'precautions' => [
                'This plan is a general guide — adjust portions based on how you feel',
                'Consult your doctor before making major dietary changes if you are on medication',
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
