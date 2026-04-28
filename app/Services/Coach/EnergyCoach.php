<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class EnergyCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId, string $inputMode = 'chat'): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $energyContext   = $this->buildEnergyContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $energyContext);

        return parent::respond($user, $enrichedMessage, $sessionId, $inputMode);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTEXT BUILDER
    // ─────────────────────────────────────────────────────────────

    private function buildEnergyContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // ── Known health conditions that directly cause fatigue ──
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $context['has_thyroid']   = str_contains($healthCond, 'thyroid') || str_contains($healthCond, 'hypothyroid') || str_contains($lower, 'thyroid') || str_contains($lower, 'tsh');
        $context['has_anaemia']   = str_contains($healthCond, 'anaemia') || str_contains($healthCond, 'anemia') || str_contains($lower, 'anaemia') || str_contains($lower, 'anemia') || str_contains($lower, 'iron deficiency');
        $context['has_diabetes']  = str_contains($healthCond, 'diabet') || str_contains($lower, 'diabet') || str_contains($lower, 'blood sugar') || str_contains($lower, 'sugar level');
        $context['has_pcos']      = str_contains($healthCond, 'pcos') || str_contains($healthCond, 'pcod') || str_contains($lower, 'pcos') || str_contains($lower, 'pcod');
        $context['has_bp']        = str_contains($healthCond, 'blood pressure') || str_contains($healthCond, 'bp') || str_contains($lower, 'blood pressure') || str_contains($lower, 'hypertension');

        // ── Fatigue type signals ──────────────────────────────────
        $context['morning_fatigue']    = preg_match('/\b(morning|wake up|get up|subah|uthna|after sleep|even after sleeping|still tired)\b/i', $message);
        $context['afternoon_crash']    = preg_match('/\b(afternoon|post lunch|after lunch|2pm|3pm|dopahar|sleepy after|lunch crash)\b/i', $message);
        $context['all_day_fatigue']    = preg_match('/\b(all day|whole day|always tired|constantly tired|no energy all|din bhar|poora din)\b/i', $message);
        $context['physical_fatigue']   = preg_match('/\b(body tired|body ache|muscle tired|heavy body|legs tired|physically drained|workout tired)\b/i', $message);
        $context['mental_fatigue']     = preg_match('/\b(brain fog|mental tired|can.t focus|concentration|memory|foggy|mentally drained|overthink|mind tired)\b/i', $message);
        $context['sudden_energy_drop'] = preg_match('/\b(sudden|crash|drop|spike|sugar crash|energy drop|suddenly tired|suddenly low)\b/i', $message);

        // ── Root cause signals ────────────────────────────────────
        $context['poor_sleep']         = preg_match('/\b(bad sleep|poor sleep|no sleep|sleep deprived|insomnia|can.t sleep|waking up|disturbed sleep|neend nahi|6 hours|5 hours|4 hours)\b/i', $message);
        $context['skipping_meals']     = preg_match('/\b(skip|no breakfast|no lunch|miss meal|forget to eat|busy no time|khana nahi khaya)\b/i', $message);
        $context['high_sugar_diet']    = preg_match('/\b(sweet|sugar|biscuit|chai|tea|coffee|cold drink|juice|maida|fried|junk|fast food)\b/i', $message);
        $context['dehydrated']         = preg_match('/\b(water|dehydrat|pani nahi|thirsty|dry mouth|headache|less water|not drinking)\b/i', $message);
        $context['sedentary']          = preg_match('/\b(sitting|desk job|no exercise|no walk|sedentary|inactive|no movement|baitha rehta)\b/i', $message);
        $context['high_stress']        = preg_match('/\b(stress|tension|anxiety|pressure|burnout|overwhelm|worried|overthink|pareshan)\b/i', $message);
        $context['overworking']        = preg_match('/\b(overwork|long hours|night shift|late night work|no rest|no break|12 hours|14 hours|working too much)\b/i', $message);

        // ── Deficiency signals ────────────────────────────────────
        $context['possible_iron']      = preg_match('/\b(pale|breathless|dizzy|iron|anaemia|anemia|hair fall|weak|cold hands|cold feet)\b/i', $message);
        $context['possible_b12']       = preg_match('/\b(b12|numbness|tingling|nerve|memory|foggy|vegetarian|vegan|pins and needles)\b/i', $message);
        $context['possible_vit_d']     = preg_match('/\b(vitamin d|d3|bone|joint pain|sunlight|indoor|no sun|muscle weakness)\b/i', $message);
        $context['possible_magnesium'] = preg_match('/\b(magnesium|cramp|muscle cramp|restless leg|can.t sleep|anxiety|chocolate craving)\b/i', $message);
        $context['possible_thyroid_low']= preg_match('/\b(weight gain|cold|constipat|dry skin|hair loss|slow|sluggish|tsh|hypothyroid)\b/i', $message);

        // ── Topic signals ─────────────────────────────────────────
        $context['asking_about_food']       = preg_match('/\b(eat|food|diet|meal|what to eat|energy food|energy drink|boost)\b/i', $message);
        $context['asking_about_exercise']   = preg_match('/\b(exercise|workout|walk|yoga|gym|movement|active|steps)\b/i', $message);
        $context['asking_about_sleep']      = preg_match('/\b(sleep|rest|nap|neend|sona|bedtime|night routine)\b/i', $message);
        $context['asking_about_supplements']= preg_match('/\b(supplement|vitamin|mineral|tablet|capsule|multivitamin|ashwagandha|shilajit)\b/i', $message);
        $context['asking_about_routine']    = preg_match('/\b(routine|schedule|morning routine|daily routine|habit|plan|structure)\b/i', $message);
        $context['asking_about_caffeine']   = preg_match('/\b(coffee|chai|tea|caffeine|energy drink|red bull|green tea|matcha)\b/i', $message);

        // ── Diet preference ───────────────────────────────────────
        $storedPref = strtolower($memory['food_preference'] ?? $user->diet_preference ?? '');
        $context['is_veg']    = str_contains($storedPref, 'veg') && !str_contains($storedPref, 'non');
        $context['is_non_veg']= str_contains($storedPref, 'non');
        $context['is_vegan']  = str_contains($storedPref, 'vegan');

        // ── Memory context ────────────────────────────────────────
        $context['sleep_pattern'] = $memory['sleep_pattern']   ?? null;
        $context['diet_habit']    = $memory['diet_habit']       ?? null;
        $context['activity']      = $memory['activity_level']   ?? $user->activity_level ?? null;
        $context['stress_level']  = $memory['stress_level']     ?? $user->stress_level   ?? null;
        $context['lifestyle']     = $memory['lifestyle']        ?? null;
        $context['challenges']    = $memory['challenges']       ?? null;
        $context['medications']   = strtolower($memory['medications'] ?? '');

        // ── Physical stats ────────────────────────────────────────
        $context['weight'] = $user->weight ?? null;
        $context['age']    = $user->getAge() > 0 ? $user->getAge() : null;
        $context['gender'] = $user->gender ?? null;

        return $context;
    }

    // ─────────────────────────────────────────────────────────────
    // MESSAGE ENRICHER
    // ─────────────────────────────────────────────────────────────

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // ── Core framing ─────────────────────────────────────────
        $lines[] = 'You are an energy and vitality coach. Fatigue is almost always caused by one or more root causes — never give generic "sleep more and eat well" advice. Identify the likely root cause from context and address it specifically.';

        // ── Known conditions that cause fatigue ──────────────────
        if ($ctx['has_thyroid']) {
            $lines[] = 'IMPORTANT: User has thyroid condition. Hypothyroidism is a major cause of fatigue, weight gain, brain fog, and sluggishness. If TSH is not controlled, energy will not improve regardless of lifestyle. Ask if their thyroid levels are currently in range. Suggest getting TSH checked if not done recently.';
        }

        if ($ctx['has_anaemia']) {
            $lines[] = 'IMPORTANT: User has anaemia/iron deficiency. This is a primary cause of fatigue, breathlessness, and weakness. Iron-rich foods: spinach, methi, rajma, chana, jaggery, sesame, pomegranate. Always pair with Vitamin C for absorption. Avoid tea/coffee within 1 hour of meals. May need iron supplement — suggest consulting doctor.';
        }

        if ($ctx['has_diabetes']) {
            $lines[] = 'User has diabetes. Blood sugar fluctuations (highs and lows) are a major cause of energy crashes. Stable blood sugar = stable energy. Advise eating every 3-4 hours, avoiding refined carbs, and checking sugar levels if feeling suddenly drained.';
        }

        if ($ctx['has_pcos']) {
            $lines[] = 'User has PCOS. Insulin resistance in PCOS causes chronic fatigue and energy crashes. Low GI diet, regular movement, and stress management are key. Also check for associated thyroid issues and iron deficiency which are common in PCOS.';
        }

        // ── Fatigue type — specific guidance ─────────────────────
        if ($ctx['morning_fatigue']) {
            $lines[] = 'Morning fatigue despite sleeping: likely causes are poor sleep quality (not duration), thyroid, anaemia, or going to bed too late. Ask about sleep quality, not just hours. Also ask about screen time before bed and room temperature.';
        }

        if ($ctx['afternoon_crash']) {
            $lines[] = 'Post-lunch energy crash: almost always caused by a high-carb/high-sugar lunch causing an insulin spike and crash. Solution: reduce rice/roti portion at lunch, add protein and vegetables, avoid sugary drinks with lunch, short 10-min walk after eating.';
        }

        if ($ctx['all_day_fatigue']) {
            $lines[] = 'All-day fatigue: this is a systemic issue. Most likely causes: iron deficiency, B12 deficiency, thyroid, poor sleep quality, chronic stress, or dehydration. Suggest getting basic blood tests: CBC, thyroid (TSH), B12, Vitamin D, iron (ferritin).';
        }

        if ($ctx['mental_fatigue']) {
            $lines[] = 'Mental fatigue / brain fog: likely causes are poor sleep, B12 deficiency, dehydration, high stress, or blood sugar instability. Magnesium and B12 are key for brain function. Suggest: 2-3L water/day, B12 check, reduce screen time, short breaks every 90 minutes.';
        }

        if ($ctx['sudden_energy_drop']) {
            $lines[] = 'Sudden energy drops: classic sign of blood sugar instability (sugar spike then crash). Happens after high-carb meals, skipping meals, or too much caffeine. Solution: eat every 3-4 hours, include protein in every meal, reduce refined carbs and sugary drinks.';
        }

        // ── Root cause — specific fixes ───────────────────────────
        if ($ctx['poor_sleep']) {
            $sleepNote = 'Poor sleep is directly causing low energy. ';
            if ($ctx['sleep_pattern']) {
                $sleepNote .= "Known sleep pattern: {$ctx['sleep_pattern']}. ";
            }
            $sleepNote .= 'Even 1-2 hours of sleep debt causes 30-40% drop in energy and focus. Priority: fix sleep before anything else. Suggest: consistent sleep/wake time, no screens 30 min before bed, room temperature 18-22°C, avoid heavy dinner.';
            $lines[] = $sleepNote;
        }

        if ($ctx['skipping_meals']) {
            $lines[] = 'User is skipping meals — this is a direct cause of energy crashes. Brain runs on glucose; skipping meals causes blood sugar to drop, leading to fatigue, irritability, and poor focus. Even a small snack (banana, handful of nuts, curd) prevents this. Never skip breakfast.';
        }

        if ($ctx['high_sugar_diet']) {
            $lines[] = 'High sugar/refined carb diet detected. This causes energy spikes followed by crashes. The "chai-biscuit" cycle is a common Indian pattern that creates energy dependency. Suggest: replace biscuits with nuts or fruit, reduce chai to 1-2 cups/day, avoid cold drinks and packaged juices.';
        }

        if ($ctx['dehydrated']) {
            $lines[] = 'Dehydration is a very common and underestimated cause of fatigue. Even 2% dehydration causes 20% drop in energy and focus. Suggest: 2.5-3L water/day, start morning with 2 glasses, set phone reminders, include coconut water or nimbu pani. Avoid excess tea/coffee — they are diuretics.';
        }

        if ($ctx['sedentary']) {
            $lines[] = 'Sedentary lifestyle is causing low energy — counterintuitive but true: the less you move, the more tired you feel. Even a 20-30 min walk daily increases mitochondrial activity and energy levels within 2 weeks. Suggest starting with 10-min walks after meals.';
        }

        if ($ctx['high_stress']) {
            $stressNote = 'Chronic stress is draining energy through cortisol overload. ';
            if ($ctx['stress_level']) {
                $stressNote .= "Known stress level: {$ctx['stress_level']}. ";
            }
            $stressNote .= 'Cortisol disrupts sleep, depletes magnesium, and causes adrenal fatigue. Suggest: 5-min deep breathing (4-7-8 technique), short walks, reducing caffeine, and identifying the main stress trigger.';
            $lines[] = $stressNote;
        }

        if ($ctx['overworking']) {
            $lines[] = 'User is overworking. Long hours without breaks cause mental and physical exhaustion. Suggest: Pomodoro technique (25 min work, 5 min break), mandatory lunch break away from screen, no work after 9pm, and at least one full rest day per week.';
        }

        // ── Deficiency intelligence ───────────────────────────────
        if ($ctx['possible_iron']) {
            $ironNote = 'Iron deficiency signals detected. ';
            if ($ctx['is_veg'] || $ctx['is_vegan']) {
                $ironNote .= 'Vegetarians are at higher risk. ';
            }
            if ($ctx['gender'] === 'female') {
                $ironNote .= 'Women lose iron monthly through menstruation — regular monitoring is important. ';
            }
            $ironNote .= 'Suggest: ferritin blood test, iron-rich foods (rajma, chana, spinach, jaggery), Vitamin C with every iron-rich meal, avoid tea/coffee with meals.';
            $lines[] = $ironNote;
        }

        if ($ctx['possible_b12']) {
            $b12Note = 'B12 deficiency signals detected. B12 is essential for energy production at cellular level. ';
            if ($ctx['is_veg'] || $ctx['is_vegan']) {
                $b12Note .= 'Vegetarians/vegans cannot get B12 from food — supplementation is mandatory (500mcg/day methylcobalamin). ';
            }
            $b12Note .= 'Symptoms: fatigue, brain fog, numbness, memory issues. Suggest B12 blood test.';
            $lines[] = $b12Note;
        }

        if ($ctx['possible_vit_d']) {
            $lines[] = 'Vitamin D deficiency signals detected. D3 deficiency causes fatigue, muscle weakness, and low mood. Over 70% of Indians are deficient. Suggest: 15-20 min morning sunlight (before 10am), D3 supplement (1000-2000 IU with K2), dietary sources: egg yolk, fatty fish, fortified milk.';
        }

        if ($ctx['possible_magnesium']) {
            $lines[] = 'Magnesium deficiency signals detected. Magnesium is involved in 300+ energy reactions in the body. Deficiency causes fatigue, muscle cramps, poor sleep, and anxiety. Food sources: pumpkin seeds, dark chocolate, spinach, almonds, banana. Supplement: magnesium glycinate 200-400mg at night.';
        }

        if ($ctx['possible_thyroid_low']) {
            $lines[] = 'Hypothyroid symptoms detected (weight gain, cold intolerance, constipation, sluggishness, hair loss). If not already diagnosed, suggest getting TSH test done. If on medication, ask if dose was recently reviewed — undertreated hypothyroidism is a very common cause of persistent fatigue.';
        }

        // ── Topic-specific guidance ───────────────────────────────
        if ($ctx['asking_about_food']) {
            $foodNote = 'Energy-boosting foods: complex carbs (oats, brown rice, sweet potato, millets), protein at every meal, iron-rich foods, magnesium-rich foods (seeds, nuts, dark leafy greens), Vitamin C foods (amla, lemon, guava). ';
            if ($ctx['is_veg']) {
                $foodNote .= 'Vegetarian energy foods: rajma, chana, paneer, curd, soya, sprouts, nuts, seeds, banana, dates.';
            } else {
                $foodNote .= 'Non-veg energy foods: eggs (best complete protein), chicken, fish (omega-3 boosts brain energy), plus all plant sources.';
            }
            $lines[] = $foodNote;
        }

        if ($ctx['asking_about_exercise']) {
            $exerciseNote = 'Exercise and energy: paradoxically, regular movement increases energy by improving mitochondrial density and oxygen delivery. ';
            $activityLevel = strtolower((string)($ctx['activity'] ?? ''));
            if (str_contains($activityLevel, 'sedentary') || str_contains($activityLevel, 'light')) {
                $exerciseNote .= 'Start small: 10-min walk after each meal (30 min total). This alone improves energy within 1-2 weeks. Avoid intense exercise when severely fatigued — it worsens adrenal fatigue.';
            } else {
                $exerciseNote .= 'Ensure adequate recovery between workouts. Overtraining causes fatigue. Include 1-2 rest days. Post-workout nutrition (protein + carbs within 30 min) is critical for recovery.';
            }
            $lines[] = $exerciseNote;
        }

        if ($ctx['asking_about_sleep']) {
            $lines[] = 'Sleep and energy: 7-9 hours is the target but quality matters more than quantity. Deep sleep (stages 3-4) is when the body repairs and restores energy. Tips: consistent sleep time (even weekends), dark and cool room, no screens 30 min before bed, avoid heavy meals within 2 hours of sleep, magnesium glycinate at night improves deep sleep.';
        }

        if ($ctx['asking_about_supplements']) {
            $suppNote = 'Energy supplements that actually work (evidence-based): B12 (if deficient), Iron (if deficient — get tested first), Vitamin D3 + K2, Magnesium glycinate (sleep and energy), Ashwagandha (adaptogen — reduces cortisol, improves stamina — 300-600mg KSM-66 extract). ';
            $suppNote .= 'Avoid: energy drinks (crash after), high-dose caffeine, unregulated supplements. Always test before supplementing iron.';
            $lines[] = $suppNote;
        }

        if ($ctx['asking_about_caffeine']) {
            $lines[] = 'Caffeine and energy: caffeine blocks adenosine (tiredness signal) but does not create real energy — it borrows from tomorrow. Limit to 1-2 cups of chai/coffee per day, avoid after 2pm (disrupts sleep), never use as meal replacement. Green tea is a better option — lower caffeine, L-theanine prevents jitteriness.';
        }

        if ($ctx['asking_about_routine']) {
            $lines[] = 'Energy-optimising daily routine: wake at consistent time, 2 glasses water immediately, light breakfast within 1 hour, 10-min morning sunlight, eat every 3-4 hours, short walk after lunch, no screens 30 min before bed, sleep by 10-11pm. Consistency matters more than perfection.';
        }

        // ── Diet preference context ───────────────────────────────
        if ($ctx['is_vegan']) {
            $lines[] = 'User is vegan. Must supplement B12 (mandatory), consider Omega-3 algae oil, iron monitoring is important, ensure adequate protein from legumes, tofu, tempeh, seeds.';
        } elseif ($ctx['is_veg']) {
            $lines[] = 'User is vegetarian. B12 supplementation strongly recommended. Iron monitoring important especially for women. Good energy foods: eggs (if eggetarian), paneer, dal, rajma, curd, nuts, seeds.';
        }

        // ── Lifestyle and challenge context ──────────────────────
        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Tailor energy advice to fit their actual daily schedule.";
        }

        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these directly rather than giving generic advice.";
        }

        // ── Age/gender specific ───────────────────────────────────
        if ($ctx['gender'] === 'female' && $ctx['age'] && $ctx['age'] >= 35) {
            $lines[] = 'Female user 35+: perimenopause can begin causing fatigue, sleep disruption, and mood changes. Also check thyroid and iron — both are more common in women over 35. Hormonal changes affect energy significantly.';
        }

        if ($ctx['age'] && $ctx['age'] >= 50) {
            $lines[] = 'User 50+: natural decline in mitochondrial function with age. Resistance training (even light) is the most effective intervention for age-related fatigue. CoQ10 supplement (100-200mg) supports mitochondrial energy production.';
        }

        // ── Build final block ─────────────────────────────────────
        $block = "ENERGY COACHING CONTEXT (identify the root cause of fatigue and address it specifically — do not give generic advice):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
