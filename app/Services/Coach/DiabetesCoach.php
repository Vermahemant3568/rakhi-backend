<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class DiabetesCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $ctx             = $this->buildDiabetesContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $ctx);

        // Store confirmed diabetes type into memory if freshly detected this turn
        $this->persistDiabetesTypeIfNew($user, $ctx);

        $response = parent::respond($user, $enrichedMessage, $sessionId);

        // Strip stiff clinical English that breaks warm Hinglish tone
        $response = str_ireplace(
            ['It is recommended', 'You are advised to', 'Based on your condition', 'It is important to note'],
            ['Behtar rahega',     'Ek kaam karein',     'Aapki situation mein',   'Ek zaroori baat'],
            $response
        );

        return $response;
    }

    // ─────────────────────────────────────────────────────────────
    // CONTEXT BUILDER
    // Reads stored memory + current message to build clinical picture
    // ─────────────────────────────────────────────────────────────

    private function buildDiabetesContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $ctx     = [];

        // ── Diabetes Type ─────────────────────────────────────────
        // Priority: stored diabetes_type memory > health_condition > current message
        $storedType      = strtolower($memory['diabetes_type']    ?? '');
        $storedCondition = strtolower($memory['health_condition'] ?? '');
        $medications     = strtolower($memory['medications']      ?? '');

        if ($storedType !== '') {
            // Trust stored type — already confirmed in a previous session
            $ctx['type']           = $storedType;
            $ctx['type_confirmed'] = true;
        } else {
            $ctx['type_confirmed'] = false;

            if (
                str_contains($storedCondition, 'type 1') ||
                str_contains($lower, 'type 1') ||
                str_contains($lower, 'type1') ||
                str_contains($lower, 't1d') ||
                str_contains($lower, 'type i diabetes') ||
                // Insulin-only patients with no oral meds strongly suggest Type 1
                (str_contains($medications, 'insulin') && !str_contains($medications, 'metformin'))
            ) {
                $ctx['type'] = 'type1';
            } elseif (
                str_contains($storedCondition, 'type 2') ||
                str_contains($lower, 'type 2') ||
                str_contains($lower, 'type2') ||
                str_contains($lower, 't2d') ||
                str_contains($lower, 'type ii diabetes')
            ) {
                $ctx['type'] = 'type2';
            } elseif (
                str_contains($storedCondition, 'gestational') ||
                str_contains($lower, 'gestational') ||
                str_contains($lower, 'pregnancy sugar') ||
                str_contains($lower, 'pregnancy mein sugar')
            ) {
                $ctx['type'] = 'gestational';
            } elseif (
                str_contains($storedCondition, 'prediabet') ||
                str_contains($lower, 'prediabet') ||
                str_contains($lower, 'pre-diabet') ||
                str_contains($lower, 'borderline sugar') ||
                str_contains($lower, 'borderline diabetes')
            ) {
                $ctx['type'] = 'prediabetes';
            } else {
                $ctx['type'] = 'unknown';
            }
        }

        // ── Insulin Usage ─────────────────────────────────────────
        // Type 1 is ALWAYS on insulin — never ask them if they take insulin
        $ctx['on_insulin'] = ($ctx['type'] === 'type1')
            || str_contains($medications, 'insulin')
            || str_contains($lower, 'insulin')
            || str_contains($lower, 'injection')
            || str_contains($lower, 'basal')
            || str_contains($lower, 'bolus')
            || str_contains($lower, 'lantus')
            || str_contains($lower, 'tresiba')
            || str_contains($lower, 'toujeo')
            || str_contains($lower, 'novorapid')
            || str_contains($lower, 'humalog')
            || str_contains($lower, 'apidra')
            || str_contains($lower, 'novomix')
            || str_contains($lower, 'mixtard');

        // ── Oral Medication ───────────────────────────────────────
        $ctx['on_oral_meds'] = str_contains($medications, 'metformin')
            || str_contains($lower, 'metformin')
            || str_contains($lower, 'glipizide')
            || str_contains($lower, 'glimepiride')
            || str_contains($lower, 'januvia')
            || str_contains($lower, 'sitagliptin')
            || str_contains($lower, 'vildagliptin')
            || str_contains($lower, 'dapagliflozin')
            || str_contains($lower, 'empagliflozin')
            || str_contains($lower, 'jardiance')
            || str_contains($lower, 'forxiga')
            || str_contains($lower, 'tablet')
            || str_contains($lower, 'goli');

        // ── Sugar Level Signals ───────────────────────────────────
        $ctx['high_sugar'] = (bool) preg_match(
            '/\b(high sugar|sugar high|sugar spike|hyperglycemi|glucose high|sugar [3-9]\d{2}|sugar [1-9]\d{3}|reading [3-9]\d{2}|hba1c [7-9]|hba1c 1[0-9]|300|350|400|450|500)\b/i',
            $message
        );

        $ctx['low_sugar'] = (bool) preg_match(
            '/\b(low sugar|sugar low|hypoglycemi|sugar drop|shaking|trembling|glucose low|below 70|below 60|sugar [3-6]\d|reading [3-6]\d|sugar 40|sugar 50|sugar 60|sugar 70|sweating badly|feeling faint)\b/i',
            $message
        );

        // ── DKA Risk (Type 1 specific — Diabetic Ketoacidosis) ────
        // DKA = high sugar + vomiting/nausea/weakness in Type 1 = EMERGENCY
        $ctx['dka_risk'] = ($ctx['type'] === 'type1')
            && $ctx['high_sugar']
            && (bool) preg_match('/\b(vomit|ulti|nausea|weakness|kamzori|breathless|fruity|ketone|dka)\b/i', $message);

        // ── Insulin Dose Question (hard block) ────────────────────
        $ctx['asking_insulin_dose'] = (bool) preg_match(
            '/\b(kitna insulin|how much insulin|insulin dose|insulin units|basal dose|bolus dose|correction dose|sliding scale|carb ratio|insulin to carb|insulin sensitivity|correction factor|insulin badhaon|insulin kam|insulin adjust|increase insulin|decrease insulin)\b/i',
            $message
        );

        // ── Injection Pain / Site Issues (Type 1 specific) ────────
        $ctx['injection_pain'] = ($ctx['on_insulin'])
            && (bool) preg_match('/\b(injection pain|injection site|jahan inject|needle dard|syringe dard|pen needle|injection jagah|lump injection|hard skin injection|lipohypertrophy)\b/i', $message);

        // ── Topic Signals ─────────────────────────────────────────
        $ctx['asking_about_food']     = (bool) preg_match('/\b(eat|food|meal|diet|rice|roti|sugar|sweet|fruit|carb|breakfast|lunch|dinner|snack|khana|khaana|nashta)\b/i', $message);
        $ctx['asking_about_exercise'] = (bool) preg_match('/\b(exercise|walk|gym|workout|yoga|run|active|steps|vyayam|kasrat)\b/i', $message);
        $ctx['asking_about_timing']   = (bool) preg_match('/\b(when|timing|time|before|after|morning|night|fasting|post.?meal|pre.?meal|subah|raat|khane ke baad|khane se pehle)\b/i', $message);
        $ctx['asking_about_numbers']  = (bool) preg_match('/\b(\d{2,3}|hba1c|a1c|fasting|pp|post.?prandial|reading|level|mg.?dl|mmol)\b/i', $message);

        $ctx['feeling_unwell'] = (bool) preg_match(
            '/\b(tired|fatigue|dizzy|weak|headache|blurry|thirsty|frequent urination|numb|tingling|pain|burning|swelling|cramp|fever|nausea|thakan|kamzori|chakkar|bukhaar|jalan|sujan|dard|ulti|pyaas|zyada peeshab|aankhein|blurry vision)\b/i',
            $message
        );

        // ── Foot / Nerve Pain (Complication Signal) ───────────────
        $ctx['foot_nerve_pain'] = (bool) preg_match('/\b(pero|pair|foot|feet|leg|toe|ankle|haath|hand|finger|arm)\b/i', $message)
            && (bool) preg_match('/\b(dard|pain|jalan|burning|numb|sujan|swelling|tingling|kamzori|weak|sensation)\b/i', $message);

        // ── Eye Symptoms (Retinopathy Signal) ─────────────────────
        $ctx['eye_symptoms'] = (bool) preg_match('/\b(aankhein|eye|vision|blurry|dhundhla|floaters|dark spot|aankhon mein)\b/i', $message)
            && (bool) preg_match('/\b(dard|pain|blurry|dhundhla|problem|issue|weak|kam dikh)\b/i', $message);

        // ── Kidney Symptoms (Nephropathy Signal) ──────────────────
        $ctx['kidney_symptoms'] = (bool) preg_match('/\b(kidney|gurda|urine|peeshab|peshab|swelling|sujan|protein urine|foamy urine|creatinine)\b/i', $message);

        // ── Memory context ────────────────────────────────────────
        $ctx['diet_habit']  = $memory['diet_habit']     ?? null;
        $ctx['meal_timing'] = $memory['diet_timing']    ?? null;
        $ctx['activity']    = $memory['activity_level'] ?? null;
        $ctx['food_pref']   = $memory['food_preference'] ?? null;

        return $ctx;
    }

    // ─────────────────────────────────────────────────────────────
    // MESSAGE ENRICHER
    // Prepends clinical intelligence block to the user message
    // so the LLM has full context without changing user's words
    // ─────────────────────────────────────────────────────────────

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // ── HARD BLOCK: Insulin dose question ────────────────────
        // Return immediately — do not pass to LLM at all
        if ($ctx['asking_insulin_dose']) {
            return $this->insulinDoseSafetyBlock() . "\n\nUSER MESSAGE: " . $message;
        }

        // ── EMERGENCY: DKA risk (Type 1 + high sugar + vomiting) ─
        if ($ctx['dka_risk']) {
            $lines[] = 'EMERGENCY ALERT: This user may be experiencing Diabetic Ketoacidosis (DKA). '
                . 'DKA is a life-threatening emergency in Type 1 diabetes. '
                . 'Respond with calm urgency: tell them to go to emergency immediately. '
                . 'Do NOT give home remedies. Do NOT delay. This is critical.';
        }

        // ── CRITICAL: Severe low sugar ────────────────────────────
        if ($ctx['low_sugar']) {
            $lines[] = 'CRITICAL: User may have low blood sugar (hypoglycemia). '
                . 'Follow the 15-15 Rule: take 15g fast-acting carbs NOW (4 glucose tablets, OR half cup juice, OR 3 teaspoons sugar in water), '
                . 'wait 15 minutes, recheck sugar. If still low, repeat. '
                . 'If unconscious or cannot swallow — call 112 immediately. '
                . 'Respond with calm urgency. Do NOT give food advice. Safety first.';
        }

        // ── HIGH SUGAR alert ──────────────────────────────────────
        if ($ctx['high_sugar'] && !$ctx['dka_risk']) {
            if ($ctx['type'] === 'type1') {
                $lines[] = 'ALERT: High blood sugar in a Type 1 patient. '
                    . 'Ask about recent meals, missed insulin, illness, or stress — these are the main causes. '
                    . 'Do NOT suggest insulin correction dose — that is doctor territory. '
                    . 'Guide on hydration (water), avoiding more carbs, and contacting their doctor if sugar stays high. '
                    . 'Watch for DKA warning signs: vomiting, weakness, fruity breath.';
            } else {
                $lines[] = 'ALERT: High blood sugar. '
                    . 'Ask about recent meals, activity, stress, and medication timing. '
                    . 'Guide on drinking water, light walk after meals, and avoiding sugary foods. '
                    . 'If sugar is consistently above 300, advise seeing their doctor.';
            }
        }

        // ── TYPE-SPECIFIC COACHING CONTEXT ───────────────────────
        switch ($ctx['type']) {
            case 'type1':
                $lines[] = 'PATIENT TYPE: Type 1 Diabetes (T1D) — autoimmune, insulin-dependent since diagnosis. '
                    . 'This patient CANNOT produce insulin at all. They MUST take insulin to survive. '
                    . 'NEVER suggest they can control sugar with diet alone or reduce insulin without doctor guidance. '
                    . 'NEVER give insulin dose, correction factor, carb ratio, or basal/bolus advice. '
                    . 'Focus on: consistent meal timing, carb awareness (not elimination), exercise safety, '
                    . 'hypoglycemia prevention, and emotional support — T1D is lifelong and mentally exhausting. '
                    . 'Always acknowledge the daily burden of living with T1D with genuine empathy.';
                break;

            case 'type2':
                $lines[] = 'PATIENT TYPE: Type 2 Diabetes (T2D) — insulin resistance, often lifestyle-related. '
                    . 'This patient may or may not be on insulin. '
                    . 'Focus on: reducing refined carbs, increasing fibre, 30-min walk after meals, '
                    . 'weight management, medication adherence, and stress reduction. '
                    . 'T2D is largely manageable with lifestyle — be encouraging and practical. '
                    . 'If on insulin: never suggest dose changes — guide on meal timing instead.';
                break;

            case 'gestational':
                $lines[] = 'PATIENT TYPE: Gestational Diabetes — diabetes during pregnancy. '
                    . 'Extra caution required. Blood sugar targets are stricter: fasting <95, post-meal <140 (1hr) or <120 (2hr). '
                    . 'Focus on: safe Indian foods, small frequent meals, gentle walking, stress reduction. '
                    . 'NEVER suggest skipping meals — baby needs consistent nutrition. '
                    . 'Always recommend regular doctor/gynaecologist monitoring. '
                    . 'Be extra warm and reassuring — pregnancy + diabetes is stressful.';
                break;

            case 'prediabetes':
                $lines[] = 'PATIENT TYPE: Prediabetes / Borderline Sugar — HbA1c 5.7–6.4%, fasting 100–125 mg/dL. '
                    . 'This is REVERSIBLE with lifestyle changes. Be motivating and positive. '
                    . 'Focus on: reducing refined carbs and sugar, increasing vegetables and protein, '
                    . 'daily 30-min walk, weight loss of even 5-7% body weight makes a huge difference. '
                    . 'Frame it as: "You caught this early — this is the best time to act."';
                break;

            default:
                $lines[] = 'PATIENT TYPE: Diabetes type not yet confirmed. '
                    . 'Ask naturally in conversation: "Kya aapko Type 1 hai ya Type 2?" '
                    . 'Do NOT assume. Type matters clinically — coaching differs significantly.';
                break;
        }

        // ── INSULIN CONTEXT (Type 1 specific guidance) ───────────
        if ($ctx['on_insulin'] && $ctx['type'] === 'type1') {
            $lines[] = 'INSULIN CONTEXT (Type 1): This patient is on insulin therapy. '
                . 'Meal timing relative to insulin is critical — never suggest skipping or delaying meals. '
                . 'Exercise can cause sugar to drop — always advise checking sugar before exercise '
                . 'and carrying fast sugar (glucose tablets, juice) during activity. '
                . 'NEVER discuss dose, units, timing of insulin injection — that is strictly doctor territory.';
        } elseif ($ctx['on_insulin'] && $ctx['type'] !== 'type1') {
            $lines[] = 'INSULIN CONTEXT (Type 2 on insulin): Meal timing matters. '
                . 'Never suggest skipping meals. Never discuss dose changes. '
                . 'Guide on consistent meal timing and carb portions instead.';
        }

        // ── ORAL MEDICATION CONTEXT ───────────────────────────────
        if ($ctx['on_oral_meds'] && !$ctx['on_insulin']) {
            $lines[] = 'MEDICATION CONTEXT: Patient is on oral diabetes medication (e.g. Metformin, Glimepiride). '
                . 'Remind naturally to take medication with meals if relevant. '
                . 'Never suggest changing dose or stopping medication.';
        }

        // ── INJECTION PAIN / SITE ISSUES (Type 1) ────────────────
        if ($ctx['injection_pain']) {
            $lines[] = 'INJECTION SITE ISSUE: Patient is reporting pain or discomfort at injection site. '
                . 'This is common and important. Guide on: rotating injection sites (abdomen, thigh, upper arm), '
                . 'not injecting into the same spot repeatedly (causes lipohypertrophy — hard lumps that affect absorption), '
                . 'using a fresh needle each time, injecting at room temperature insulin. '
                . 'Acknowledge the daily discomfort of injections with genuine empathy — it is hard.';
        }

        // ── FOOT / NERVE PAIN ─────────────────────────────────────
        if ($ctx['foot_nerve_pain']) {
            $lines[] = 'COMPLICATION SIGNAL: Patient reports pain, burning, numbness, or tingling in feet/legs/hands. '
                . 'In diabetics, this can indicate peripheral neuropathy (nerve damage from prolonged high sugar) '
                . 'or peripheral vascular disease (poor blood flow). Both are serious. '
                . 'Respond with empathy first. Gently explain that high sugar over time can affect nerves and blood flow. '
                . 'Ask how long this has been happening and whether sugar has been well-controlled recently. '
                . 'Strongly advise seeing their doctor — do not alarm, but do not minimise. '
                . 'For Type 1: neuropathy can appear earlier. For Type 2: often tied to years of poor control.';
        }

        // ── EYE SYMPTOMS ──────────────────────────────────────────
        if ($ctx['eye_symptoms']) {
            $lines[] = 'COMPLICATION SIGNAL: Patient reports eye/vision symptoms. '
                . 'In diabetics, blurry vision or eye problems can indicate diabetic retinopathy (damage to eye blood vessels). '
                . 'This is serious and can lead to vision loss if untreated. '
                . 'Respond with empathy. Advise seeing an ophthalmologist urgently. '
                . 'Do not minimise. Annual eye check is essential for all diabetics.';
        }

        // ── KIDNEY SYMPTOMS ───────────────────────────────────────
        if ($ctx['kidney_symptoms']) {
            $lines[] = 'COMPLICATION SIGNAL: Patient mentions kidney-related symptoms or terms. '
                . 'Diabetic nephropathy (kidney damage) is a serious long-term complication. '
                . 'Advise seeing their doctor for kidney function tests (creatinine, urine protein). '
                . 'Guide on reducing salt, staying hydrated, and controlling blood pressure alongside sugar.';
        }

        // ── FOOD GUIDANCE ─────────────────────────────────────────
        if ($ctx['asking_about_food']) {
            $foodNote = 'FOOD GUIDANCE: Give practical Indian food examples. '
                . 'Never say "avoid carbs" — say "roti 1-2 rakho, sabzi zyada lo". '
                . 'Safe choices: daliya, brown rice (small portion), dal, sabzi, salad, eggs, curd (plain). '
                . 'Limit: white rice (large portions), maida, fried food, packaged snacks, fruit juice, sweets. '
                . 'For Type 1: carb awareness matters (consistent portions), not elimination. '
                . 'For Type 2: reducing refined carbs and increasing fibre is the priority. '
                . 'For Gestational: small frequent meals, no skipping, avoid high-GI foods.';
            if ($ctx['food_pref'])  $foodNote .= " User food preference: {$ctx['food_pref']}.";
            if ($ctx['diet_habit']) $foodNote .= " Known diet habit: {$ctx['diet_habit']}.";
            $lines[] = $foodNote;
        }

        // ── EXERCISE GUIDANCE ─────────────────────────────────────
        if ($ctx['asking_about_exercise']) {
            if ($ctx['type'] === 'type1') {
                $lines[] = 'EXERCISE (Type 1): Exercise is beneficial but needs care. '
                    . 'Check sugar BEFORE exercise — if below 100, have a small snack first. '
                    . 'Always carry fast sugar (glucose tablets or juice) during exercise. '
                    . 'Aerobic exercise (walking, cycling) can lower sugar during activity. '
                    . 'Intense exercise can sometimes raise sugar temporarily. '
                    . 'Check sugar after exercise too. '
                    . 'NEVER advise adjusting insulin for exercise — that is doctor territory.';
            } else {
                $lines[] = 'EXERCISE (Type 2/Prediabetes): 30-min brisk walk after meals is highly effective at lowering post-meal sugar. '
                    . 'Even 10-min walks 3x a day work well. '
                    . 'Avoid intense exercise if sugar is above 250 mg/dL. '
                    . 'Strength training 2-3x per week also improves insulin sensitivity significantly.';
            }
        }

        // ── MEAL TIMING ───────────────────────────────────────────
        if ($ctx['asking_about_timing']) {
            $lines[] = 'MEAL TIMING: '
                . 'For Type 1: meal timing must be consistent and aligned with insulin — skipping meals risks hypoglycemia. '
                . 'For Type 2: eating every 3-4 hours prevents sugar spikes and crashes. '
                . 'Fasting sugar is best checked first thing in the morning before eating or drinking anything. '
                . 'Post-meal sugar is checked 2 hours after the first bite of a meal.';
        }

        // ── SUGAR NUMBERS ─────────────────────────────────────────
        if ($ctx['asking_about_numbers']) {
            $lines[] = 'SUGAR TARGETS (share naturally if asked): '
                . 'Normal: Fasting 70–100 mg/dL, Post-meal (2hr) <140 mg/dL, HbA1c <5.7%. '
                . 'Prediabetes: Fasting 100–125, HbA1c 5.7–6.4%. '
                . 'Diabetes: Fasting ≥126, HbA1c ≥6.5%. '
                . 'Type 1 target (ADA): Fasting 80–130, Post-meal <180, HbA1c <7%. '
                . 'Type 2 target: Fasting 80–130, Post-meal <180, HbA1c <7% (or as advised by doctor). '
                . 'Gestational: Fasting <95, 1hr post-meal <140, 2hr post-meal <120.';
        }

        // ── FEELING UNWELL ────────────────────────────────────────
        if ($ctx['feeling_unwell'] && !$ctx['low_sugar'] && !$ctx['high_sugar']) {
            $lines[] = 'UNWELL: Patient is not feeling well. '
                . 'Start with empathy. Ask about their recent sugar reading if they have a glucometer. '
                . 'Common diabetes symptoms: fatigue (high sugar), dizziness (low sugar), '
                . 'frequent urination + thirst (high sugar), blurry vision (sugar fluctuation). '
                . 'Help them connect symptoms to sugar levels gently.';
        }

        // ── ACTIVITY MEMORY ───────────────────────────────────────
        if ($ctx['activity']) {
            $lines[] = "Known activity level: {$ctx['activity']}.";
        }

        // ── CORE RESPONSE RULES ───────────────────────────────────
        $lines[] = 'RESPONSE RULES: '
            . '2–3 short lines max. One question only. '
            . 'Start with empathy or acknowledgement, then one practical point, then one question. '
            . 'Use simple daily language — no medical jargon. '
            . 'Always use "aap" — never "tum" or "tu". '
            . 'Reference memory naturally: "Aap pehle bata rahe the ki..." '
            . 'Never give long lists. Never sound like a health website.';

        $lines[] = 'ABSOLUTE RULES: '
            . 'NEVER give insulin dose, units, correction factor, carb ratio, or basal/bolus advice. '
            . 'NEVER suggest stopping or changing any medication. '
            . 'NEVER say "just diet and exercise" to a Type 1 patient — they NEED insulin to survive.';

        // ── BUILD FINAL BLOCK ─────────────────────────────────────
        $diabetesBlock = "DIABETES COACHING CONTEXT (use this intelligence naturally — do not quote it directly):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $diabetesBlock . "\n\nUSER MESSAGE: " . $message;
    }

    // ─────────────────────────────────────────────────────────────
    // INSULIN DOSE SAFETY BLOCK
    // Returns a safe redirect prompt when user asks about insulin dose
    // ─────────────────────────────────────────────────────────────

    private function insulinDoseSafetyBlock(): string
    {
        return 'SAFETY OVERRIDE — INSULIN DOSE QUESTION DETECTED: '
            . 'The user is asking about insulin dosing, units, correction, or adjustment. '
            . 'You MUST NOT give any insulin dose guidance whatsoever. '
            . 'Respond warmly and honestly: explain that insulin dosing depends on their specific readings, '
            . 'weight, activity, and other factors that only their doctor or diabetologist can safely assess. '
            . 'Then redirect to what you CAN help with: meal timing, food choices, exercise, and sugar monitoring. '
            . 'Keep it warm, not dismissive. Acknowledge that managing insulin is genuinely hard.';
    }

    // ─────────────────────────────────────────────────────────────
    // PERSIST DIABETES TYPE TO MEMORY
    // Stores confirmed type so it is not re-detected every session
    // ─────────────────────────────────────────────────────────────

    private function persistDiabetesTypeIfNew(User $user, array $ctx): void
    {
        if ($ctx['type'] === 'unknown' || $ctx['type_confirmed']) {
            return;
        }

        // Only store if not already saved
        $existing = UserMemory::where('user_id', $user->id)
            ->where('key', 'diabetes_type')
            ->value('value');

        if (empty($existing)) {
            UserMemory::updateOrCreate(
                ['user_id' => $user->id, 'key' => 'diabetes_type'],
                ['value' => $ctx['type'], 'source' => 'auto_detected']
            );
        }
    }
}
