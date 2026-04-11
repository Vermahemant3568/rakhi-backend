<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class DiabetesCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        // Detect diabetes type and context from memory + current message
        $diabetesContext = $this->buildDiabetesContext($user, $message);

        // Inject diabetes intelligence into the message before passing to base
        $enrichedMessage = $this->enrichMessage($message, $diabetesContext);

        // Use base respond with enriched message
        return parent::respond($user, $enrichedMessage, $sessionId);
    }

    /**
     * Build a structured diabetes context from stored memory + current message signals.
     */
    private function buildDiabetesContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // --- Diabetes Type Detection ---
        $storedCondition = strtolower($memory['health_condition'] ?? '');

        if (str_contains($storedCondition, 'type 1') || str_contains($lower, 'type 1') || str_contains($lower, 't1d') || str_contains($lower, 'type1')) {
            $context['type'] = 'type1';
        } elseif (str_contains($storedCondition, 'type 2') || str_contains($lower, 'type 2') || str_contains($lower, 't2d') || str_contains($lower, 'type2')) {
            $context['type'] = 'type2';
        } elseif (str_contains($storedCondition, 'gestational') || str_contains($lower, 'gestational')) {
            $context['type'] = 'gestational';
        } elseif (str_contains($storedCondition, 'pre') || str_contains($lower, 'prediabet') || str_contains($lower, 'pre-diabet') || str_contains($lower, 'borderline')) {
            $context['type'] = 'prediabetes';
        } else {
            $context['type'] = 'unknown';
        }

        // --- Insulin Usage ---
        $medications = strtolower($memory['medications'] ?? '');
        $context['on_insulin'] = str_contains($medications, 'insulin')
            || str_contains($lower, 'insulin')
            || str_contains($lower, 'injection')
            || str_contains($lower, 'basal')
            || str_contains($lower, 'bolus')
            || str_contains($lower, 'lantus')
            || str_contains($lower, 'novorapid')
            || str_contains($lower, 'humalog');

        // --- Oral Medication ---
        $context['on_oral_meds'] = str_contains($medications, 'metformin')
            || str_contains($lower, 'metformin')
            || str_contains($lower, 'glipizide')
            || str_contains($lower, 'januvia')
            || str_contains($lower, 'tablet')
            || str_contains($lower, 'medicine')
            || str_contains($lower, 'dapagliflozin')
            || str_contains($lower, 'jardiance');

        // --- Sugar Level Signals ---
        $context['high_sugar'] = preg_match('/\b(high sugar|sugar high|sugar spike|hyperglycemi|glucose high|300|400|500|hba1c [7-9]|hba1c 1[0-9])\b/i', $message);
        $context['low_sugar']  = preg_match('/\b(low sugar|sugar low|hypoglycemi|sugar drop|shaking|dizzy|sweating|glucose low|below 70|below 60)\b/i', $message);

        // --- Topic Signals ---
        $context['asking_about_food']    = preg_match('/\b(eat|food|meal|diet|rice|roti|sugar|sweet|fruit|carb|breakfast|lunch|dinner|snack)\b/i', $message);
        $context['asking_about_exercise'] = preg_match('/\b(exercise|walk|gym|workout|yoga|run|active|steps)\b/i', $message);
        $context['asking_about_timing']   = preg_match('/\b(when|timing|time|before|after|morning|night|fasting|post.?meal|pre.?meal)\b/i', $message);
        $context['asking_about_numbers']  = preg_match('/\b(\d{2,3}|hba1c|a1c|fasting|pp|post.?prandial|reading|level|mg.?dl|mmol)\b/i', $message);
        $context['feeling_unwell']        = preg_match('/\b(tired|fatigue|dizzy|weak|headache|blurry|thirsty|frequent urination|numb|tingling)\b/i', $message);

        // --- Diet context from memory ---
        $context['diet_habit']    = $memory['diet_habit']    ?? null;
        $context['meal_timing']   = $memory['diet_timing']   ?? null;
        $context['activity']      = $memory['activity_level'] ?? null;
        $context['food_pref']     = $memory['food_preference'] ?? null;

        return $context;
    }

    /**
     * Prepend a diabetes intelligence block to the message so the LLM
     * has full clinical context without changing the user's actual words.
     */
    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // Type-specific coaching context
        $typeNote = match($ctx['type']) {
            'type1'       => 'User has Type 1 diabetes (autoimmune, insulin-dependent). Focus on insulin timing, carb counting, and avoiding hypos.',
            'type2'       => 'User has Type 2 diabetes (lifestyle/metabolic). Focus on blood sugar control through diet, activity, and medication adherence.',
            'gestational' => 'User has gestational diabetes. Be extra careful — focus on safe blood sugar ranges, safe foods, and stress reduction.',
            'prediabetes' => 'User has prediabetes/borderline sugar. This is reversible — focus on diet changes, weight, and activity to prevent progression.',
            default       => 'Diabetes type not confirmed yet. Ask naturally if not already known.',
        };
        $lines[] = $typeNote;

        // Medication context
        if ($ctx['on_insulin']) {
            $lines[] = 'User is on insulin. Meal timing relative to insulin doses is critical. Never suggest skipping meals.';
        } elseif ($ctx['on_oral_meds']) {
            $lines[] = 'User is on oral diabetes medication. Remind about taking meds with meals if relevant.';
        }

        // Urgent sugar signals
        if ($ctx['high_sugar']) {
            $lines[] = 'ALERT: User may be experiencing high blood sugar. Acknowledge this first. Ask about recent meals, medication, and stress. Do NOT panic them.';
        }
        if ($ctx['low_sugar']) {
            $lines[] = 'ALERT: User may be experiencing low blood sugar (hypoglycemia). This is urgent. Advise 15g fast-acting carbs immediately (juice, glucose tablet, sugar water). Ask if they are safe.';
        }

        // Topic-specific intelligence hints
        if ($ctx['asking_about_food']) {
            $foodNote = 'When advising on food: focus on glycemic index, portion size, and meal timing. ';
            if ($ctx['food_pref']) {
                $foodNote .= "User prefers {$ctx['food_pref']} food. ";
            }
            if ($ctx['diet_habit']) {
                $foodNote .= "Known diet habit: {$ctx['diet_habit']}. ";
            }
            $foodNote .= 'Suggest practical Indian food swaps (e.g. brown rice over white, roti with sabzi over plain roti, avoid maida).';
            $lines[] = $foodNote;
        }

        if ($ctx['asking_about_timing']) {
            $lines[] = 'Meal timing matters greatly for diabetics. For Type 2: eating every 3-4 hours prevents spikes. For Type 1: timing must align with insulin. Fasting blood sugar is best checked before breakfast.';
        }

        if ($ctx['asking_about_numbers']) {
            $lines[] = 'Normal ranges: Fasting 70-100 mg/dL, Post-meal (2hr) <140 mg/dL, HbA1c <5.7% normal, 5.7-6.4% prediabetes, ≥6.5% diabetes. Share these naturally if user asks about their readings.';
        }

        if ($ctx['asking_about_exercise']) {
            $lines[] = 'Exercise lowers blood sugar. For Type 1: monitor sugar before/after exercise, carry fast sugar. For Type 2: 30 min walk after meals is highly effective. Avoid intense exercise if sugar >250 mg/dL.';
        }

        if ($ctx['feeling_unwell']) {
            $lines[] = 'User may be experiencing diabetes symptoms (fatigue, dizziness, thirst, frequent urination are common). Acknowledge how they feel first. Ask about their recent sugar reading if they have a glucometer.';
        }

        if ($ctx['activity']) {
            $lines[] = "User activity level: {$ctx['activity']}.";
        }

        // Build the enriched prompt prefix
        $diabetesBlock = "DIABETES COACHING CONTEXT (use this intelligence naturally in your response):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $diabetesBlock . "\n\nUSER MESSAGE: " . $message;
    }
}
