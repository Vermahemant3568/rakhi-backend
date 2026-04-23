<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class PCOSThyroidCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $ctx             = $this->buildContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $ctx);

        return parent::respond($user, $enrichedMessage, $sessionId);
    }

    private function buildContext(User $user, string $message): array
    {
        $memory     = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower      = strtolower($message);
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $ctx        = [];

        // ── Condition detection ───────────────────────────────────
        $ctx['has_pcos']    = str_contains($healthCond, 'pcos') || str_contains($healthCond, 'pcod')
            || str_contains($lower, 'pcos') || str_contains($lower, 'pcod');
        $ctx['has_thyroid'] = str_contains($healthCond, 'thyroid')
            || str_contains($lower, 'thyroid') || str_contains($lower, 'tsh');
        $ctx['has_both']    = $ctx['has_pcos'] && $ctx['has_thyroid'];

        // ── Thyroid type ──────────────────────────────────────────
        $ctx['hypothyroid'] = str_contains($healthCond, 'hypothyroid') || str_contains($lower, 'hypothyroid')
            || str_contains($lower, 'tsh high') || str_contains($lower, 'underactive');
        $ctx['hyperthyroid'] = str_contains($healthCond, 'hyperthyroid') || str_contains($lower, 'hyperthyroid')
            || str_contains($lower, 'tsh low') || str_contains($lower, 'overactive') || str_contains($lower, 'graves');
        $ctx['hashimotos']  = str_contains($lower, 'hashimoto') || str_contains($lower, 'autoimmune thyroid');

        // ── Medication ────────────────────────────────────────────
        $meds = strtolower($memory['medications'] ?? '');
        $ctx['on_thyroid_meds'] = str_contains($meds, 'thyronorm') || str_contains($meds, 'thyroxine')
            || str_contains($meds, 'levothyroxine') || str_contains($meds, 'eltroxin')
            || str_contains($lower, 'thyronorm') || str_contains($lower, 'levothyroxine');
        $ctx['on_metformin'] = str_contains($meds, 'metformin') || str_contains($lower, 'metformin');

        // ── PCOS symptom signals ──────────────────────────────────
        $ctx['irregular_periods'] = preg_match('/\b(irregular|missed period|late period|no period|period skip|cycle irregular|periods nahi)\b/i', $message);
        $ctx['hair_fall']         = preg_match('/\b(hair fall|hair loss|baal girna|thinning hair|bald)\b/i', $message);
        $ctx['facial_hair']       = preg_match('/\b(facial hair|chin hair|upper lip|unwanted hair|hirsutism|baal face)\b/i', $message);
        $ctx['acne']              = preg_match('/\b(acne|pimple|breakout|skin|hormonal acne|chin acne)\b/i', $message);
        $ctx['weight_gain']       = preg_match('/\b(weight gain|gaining weight|vajan badh|can.t lose|weight nahi)\b/i', $message);
        $ctx['mood_swings']       = preg_match('/\b(mood swing|irritable|emotional|mood change|anxiety pcos|depression pcos)\b/i', $message);
        $ctx['fertility']         = preg_match('/\b(pregnant|conceive|fertility|baby|ovulation|trying to conceive|ttc|garbh)\b/i', $message);
        $ctx['insulin_resistance']= preg_match('/\b(insulin resistance|insulin|blood sugar|sugar craving|sweet craving|energy crash)\b/i', $message);

        // ── Thyroid symptom signals ───────────────────────────────
        $ctx['fatigue']        = preg_match('/\b(tired|fatigue|thakan|no energy|exhausted|sluggish|always tired)\b/i', $message);
        $ctx['weight_issue']   = preg_match('/\b(weight|vajan|gain|lose|fat|slim|metabolism)\b/i', $message);
        $ctx['brain_fog']      = preg_match('/\b(brain fog|memory|forget|concentration|focus|foggy|confused)\b/i', $message);
        $ctx['cold_intolerance']= preg_match('/\b(cold|always cold|feel cold|cold hands|cold feet|thanda)\b/i', $message);
        $ctx['constipation']   = preg_match('/\b(constipat|bowel|digestion|stomach|pet saaf nahi|kabz)\b/i', $message);
        $ctx['dry_skin_hair']  = preg_match('/\b(dry skin|dry hair|rough skin|brittle nails|skin dry|baal dry)\b/i', $message);
        $ctx['tsh_question']   = preg_match('/\b(tsh|t3|t4|thyroid level|thyroid test|normal range|tsh kitna)\b/i', $message);

        // ── Topic signals ─────────────────────────────────────────
        $ctx['asking_about_diet']     = preg_match('/\b(eat|food|diet|meal|what to eat|khana|avoid|kya khana)\b/i', $message);
        $ctx['asking_about_exercise'] = preg_match('/\b(exercise|workout|walk|gym|yoga|movement|active)\b/i', $message);
        $ctx['asking_about_stress']   = preg_match('/\b(stress|tension|anxiety|cortisol|relax|calm)\b/i', $message);
        $ctx['asking_about_sleep']    = preg_match('/\b(sleep|neend|rest|insomnia|sona)\b/i', $message);
        $ctx['asking_about_supplements'] = preg_match('/\b(supplement|vitamin|inositol|spearmint|ashwagandha|selenium|zinc|omega)\b/i', $message);
        $ctx['asking_about_periods']  = preg_match('/\b(period|cycle|menstrual|irregular|regulate|period aana|cycle theek)\b/i', $message);

        // ── Memory ────────────────────────────────────────────────
        $ctx['diet_habit']  = $memory['diet_habit']     ?? null;
        $ctx['activity']    = $memory['activity_level'] ?? $user->activity_level ?? null;
        $ctx['stress']      = $memory['stress_level']   ?? $user->stress_level   ?? null;
        $ctx['sleep']       = $memory['sleep_pattern']  ?? null;
        $ctx['lifestyle']   = $memory['lifestyle']      ?? null;
        $ctx['challenges']  = $memory['challenges']     ?? null;

        // ── Physical stats ────────────────────────────────────────
        $pref = strtolower($memory['food_preference'] ?? $user->diet_preference ?? '');
        $ctx['is_veg']    = str_contains($pref, 'veg') && !str_contains($pref, 'non');
        $ctx['is_non_veg']= str_contains($pref, 'non');
        $ctx['age']       = $user->getAge() > 0 ? $user->getAge() : null;
        $ctx['weight']    = $user->weight ?? null;
        $ctx['height']    = $user->height ?? null;

        return $ctx;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // ── Core framing ──────────────────────────────────────────
        if ($ctx['has_both']) {
            $lines[] = 'User has BOTH PCOS and thyroid condition — this combination is common and requires careful management. Both conditions affect hormones, metabolism, and weight. Address both simultaneously. Never give advice that worsens one condition while helping the other.';
        } elseif ($ctx['has_pcos']) {
            $lines[] = 'User has PCOS (Polycystic Ovary Syndrome). PCOS is a hormonal condition affecting 1 in 5 Indian women. It is NOT just about irregular periods — it affects weight, skin, hair, mood, fertility, and long-term metabolic health. Be empathetic — PCOS is often dismissed or misunderstood.';
        } elseif ($ctx['has_thyroid']) {
            $lines[] = 'User has thyroid condition. Thyroid affects every cell in the body — metabolism, energy, mood, weight, hair, skin, digestion. Symptoms are often vague and dismissed. Validate their experience — thyroid issues are real and significantly impact quality of life.';
        }

        // ── Thyroid type specific ─────────────────────────────────
        if ($ctx['hypothyroid']) {
            $lines[] = 'HYPOTHYROIDISM: underactive thyroid — TSH is high, T3/T4 are low. Causes: fatigue, weight gain, cold intolerance, constipation, dry skin/hair, brain fog, depression, slow heart rate. Treatment: levothyroxine (Thyronorm). Key point: medication must be taken on empty stomach, 30-60 min before food, with water only. No calcium, iron, or coffee within 4 hours of medication.';
        }

        if ($ctx['hyperthyroid']) {
            $lines[] = 'HYPERTHYROIDISM: overactive thyroid — TSH is low, T3/T4 are high. Causes: weight loss, rapid heartbeat, anxiety, heat intolerance, tremors, insomnia, frequent bowel movements. Avoid: iodine-rich foods (seaweed, excess iodised salt), stimulants (caffeine, energy drinks). Exercise should be moderate — avoid high intensity until levels are controlled.';
        }

        if ($ctx['hashimotos']) {
            $lines[] = 'HASHIMOTO\'S THYROIDITIS: autoimmune thyroid condition. Gluten sensitivity is common — some patients improve significantly on gluten-free diet (worth trying for 3 months). Selenium (200mcg/day) reduces thyroid antibodies. Avoid excess iodine. Stress management is critical — stress worsens autoimmune conditions.';
        }

        if ($ctx['on_thyroid_meds']) {
            $lines[] = 'User is on thyroid medication. Critical reminder: take on empty stomach, 30-60 min before breakfast, with plain water only. Avoid calcium, iron supplements, antacids, and coffee within 4 hours. Consistent timing matters. Never skip doses. TSH should be checked every 6 months or when symptoms change.';
        }

        // ── PCOS specific ─────────────────────────────────────────
        if ($ctx['has_pcos']) {
            $lines[] = 'PCOS core mechanism: most PCOS is driven by insulin resistance — high insulin triggers ovaries to produce excess androgens (male hormones), causing irregular periods, hair fall, facial hair, acne, and weight gain. Reducing insulin resistance is the primary treatment strategy alongside medication.';
        }

        if ($ctx['on_metformin']) {
            $lines[] = 'User is on Metformin for PCOS. Metformin improves insulin sensitivity. Take with food to reduce nausea. It works best combined with diet and exercise changes. Do not suggest stopping medication.';
        }

        if ($ctx['irregular_periods']) {
            $lines[] = 'Irregular periods in PCOS: caused by anovulation (no ovulation). Lifestyle changes (weight loss of even 5-10%, low GI diet, exercise) can restore regular cycles in many women. Inositol (myo-inositol 2g + d-chiro-inositol 50mg) has strong evidence for improving cycle regularity — suggest consulting doctor. Stress and poor sleep also disrupt cycles.';
        }

        if ($ctx['hair_fall']) {
            $lines[] = 'Hair fall in PCOS/thyroid: caused by excess androgens (PCOS) or low thyroid hormones. Check ferritin (iron stores) — low ferritin is a very common cause of hair fall in Indian women. Also check B12 and Vitamin D. Spearmint tea (2 cups/day) has evidence for reducing androgens in PCOS. Biotin helps but only if deficient.';
        }

        if ($ctx['facial_hair']) {
            $lines[] = 'Facial/unwanted hair (hirsutism) in PCOS: caused by excess androgens. Spearmint tea (2 cups/day) reduces androgen levels. Inositol helps. Medical options: spironolactone, OCP — suggest consulting gynaecologist. Lifestyle: reducing insulin resistance reduces androgen production over time. Results take 3-6 months.';
        }

        if ($ctx['acne']) {
            $lines[] = 'Hormonal acne in PCOS: typically on chin, jawline, and lower face — driven by androgens. Reducing sugar and dairy often helps significantly. Low GI diet reduces insulin → reduces androgens → reduces acne. Spearmint tea helps. Zinc supplement (30mg/day) has evidence for hormonal acne. Medical: OCP or spironolactone — suggest consulting dermatologist/gynaecologist.';
        }

        if ($ctx['fertility']) {
            $lines[] = 'Fertility and PCOS: PCOS is the most common cause of anovulatory infertility but is very treatable. Weight loss of 5-10% restores ovulation in many women. Inositol improves egg quality. Medical options: letrozole, clomiphene — suggest consulting gynaecologist/fertility specialist. Do NOT give false hope or alarm — be warm and factual.';
        }

        if ($ctx['insulin_resistance']) {
            $lines[] = 'Insulin resistance in PCOS: the root cause for most symptoms. Strategy: low GI diet (avoid refined carbs, sugar, white rice large portions), eat every 3-4 hours, include protein in every meal, strength training 3x/week (most effective for insulin sensitivity), reduce stress (cortisol worsens insulin resistance), adequate sleep.';
        }

        // ── Shared PCOS + Thyroid symptoms ───────────────────────
        if ($ctx['fatigue']) {
            $lines[] = 'Fatigue in PCOS/thyroid: check TSH (thyroid), ferritin (iron), B12, and Vitamin D — all commonly deficient and all cause fatigue. In PCOS, blood sugar instability causes energy crashes. In hypothyroidism, low T3 directly causes cellular energy deficit. Address the root cause, not just the symptom.';
        }

        if ($ctx['weight_gain']) {
            $lines[] = 'Weight gain in PCOS/thyroid: both conditions make weight loss harder. In PCOS: insulin resistance causes fat storage especially around abdomen. In hypothyroidism: slow metabolism causes weight gain. Strategy: low GI diet, strength training, adequate sleep, stress management. Realistic expectation: slower weight loss than average — 0.25-0.5 kg/week is good progress.';
        }

        if ($ctx['brain_fog']) {
            $lines[] = 'Brain fog: common in both hypothyroidism (low T3 affects brain function) and PCOS (blood sugar instability affects cognition). Check TSH and blood sugar. B12 and Vitamin D deficiency also cause brain fog. Omega-3 (walnuts, flaxseed, fish) supports brain function. Stable blood sugar through regular meals reduces cognitive fluctuations.';
        }

        if ($ctx['tsh_question']) {
            $lines[] = 'TSH reference ranges: Normal: 0.5-4.5 mIU/L. Subclinical hypothyroidism: 4.5-10 (may or may not need treatment). Overt hypothyroidism: >10 (treatment needed). Hyperthyroidism: <0.5. Optimal for most people: 1-2.5 mIU/L. Pregnancy: stricter targets (<2.5 in first trimester). Note: symptoms can occur even within "normal" range — discuss with doctor.';
        }

        // ── Diet guidance ─────────────────────────────────────────
        if ($ctx['asking_about_diet']) {
            $dietNote = '';
            if ($ctx['has_pcos']) {
                $dietNote .= 'PCOS diet: low GI is the foundation. Avoid: white rice (large portions), maida, sugar, packaged food, sugary drinks. Include: whole grains (brown rice, oats, millets — jowar/bajra/ragi), vegetables, dal, protein at every meal. Anti-inflammatory foods: turmeric, ginger, berries, walnuts, flaxseed. ';
            }
            if ($ctx['has_thyroid'] && $ctx['hypothyroid']) {
                $dietNote .= 'Hypothyroid diet: avoid raw cruciferous vegetables in large amounts (cabbage, broccoli, cauliflower — cooking neutralises goitrogens). Include selenium-rich foods (Brazil nuts, sunflower seeds, eggs). Iodine from iodised salt is sufficient — do not over-supplement. ';
            }
            if ($ctx['has_thyroid'] && $ctx['hyperthyroid']) {
                $dietNote .= 'Hyperthyroid diet: avoid excess iodine (seaweed, kelp supplements). Include calcium-rich foods (dairy, ragi) — hyperthyroidism depletes bone density. ';
            }
            if ($ctx['is_veg']) {
                $dietNote .= 'Vegetarian protein sources: dal, rajma, chana, paneer (limited), curd, soya, tofu, eggs if eggetarian.';
            }
            if ($ctx['diet_habit']) {
                $dietNote .= " Known diet habit: {$ctx['diet_habit']}.";
            }
            $lines[] = 'Diet guidance: ' . $dietNote;
        }

        // ── Exercise guidance ─────────────────────────────────────
        if ($ctx['asking_about_exercise']) {
            $exNote = '';
            if ($ctx['has_pcos']) {
                $exNote .= 'PCOS exercise: strength training 3x/week is the most effective for insulin resistance. Moderate cardio 2-3x/week. AVOID excessive cardio (>60 min daily) — raises cortisol which worsens hormonal imbalance. Yoga is excellent for PCOS — reduces cortisol and improves insulin sensitivity. ';
            }
            if ($ctx['has_thyroid'] && $ctx['hypothyroid']) {
                $exNote .= 'Hypothyroid exercise: start slow — fatigue is real. Even 20-30 min walk daily is beneficial. Strength training helps counter slow metabolism. Do not push through extreme fatigue — rest is also important. ';
            }
            if ($ctx['has_thyroid'] && $ctx['hyperthyroid']) {
                $exNote .= 'Hyperthyroid exercise: avoid high intensity until levels are controlled — heart rate is already elevated. Walking and gentle yoga are safe. ';
            }
            $lines[] = 'Exercise guidance: ' . $exNote;
        }

        // ── Supplements ───────────────────────────────────────────
        if ($ctx['asking_about_supplements']) {
            $suppNote = 'Evidence-based supplements: ';
            if ($ctx['has_pcos']) {
                $suppNote .= 'PCOS: Myo-inositol (2g/day) + D-chiro-inositol (50mg/day) — improves insulin sensitivity, cycle regularity, egg quality. Spearmint tea (2 cups/day) — reduces androgens. Vitamin D (most PCOS women are deficient). Omega-3 (reduces inflammation). Zinc (30mg/day for acne and hair). ';
            }
            if ($ctx['has_thyroid']) {
                $suppNote .= 'Thyroid: Selenium (200mcg/day) — reduces thyroid antibodies in Hashimoto\'s. Vitamin D. Magnesium. Avoid: excess iodine supplements, biotin (interferes with thyroid tests — stop 3 days before blood test). ';
            }
            $suppNote .= 'Always consult doctor before starting supplements.';
            $lines[] = $suppNote;
        }

        // ── Stress and sleep ──────────────────────────────────────
        if ($ctx['asking_about_stress']) {
            $lines[] = 'Stress management for PCOS/thyroid: cortisol directly worsens both conditions. For PCOS: cortisol increases insulin resistance and androgen production. For thyroid: stress suppresses T3 conversion. Practical: 10-min daily breathing (Anulom Vilom is excellent), limit news/social media, adequate sleep, gentle yoga. This is not optional — it is medical management.';
        }

        if ($ctx['asking_about_sleep']) {
            $lines[] = 'Sleep for PCOS/thyroid: poor sleep worsens insulin resistance (PCOS) and suppresses thyroid function. Target 7-9 hours. Consistent sleep time matters. Melatonin is disrupted in PCOS — avoid screens 30 min before bed. Magnesium glycinate (200mg at night) improves sleep quality.';
        }

        // ── Periods ───────────────────────────────────────────────
        if ($ctx['asking_about_periods']) {
            $lines[] = 'Regulating periods in PCOS: lifestyle changes (low GI diet, exercise, stress management, weight loss if overweight) can restore cycles in 3-6 months. Inositol helps. Medical options: OCP (regulates cycle but does not treat root cause), progesterone, letrozole. Suggest consulting gynaecologist for personalised treatment plan.';
        }

        // ── Memory context ────────────────────────────────────────
        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Tailor advice to fit their actual schedule.";
        }
        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these directly.";
        }

        $block = "PCOS/THYROID COACHING CONTEXT (use this intelligence naturally — validate their experience, then give specific actionable guidance):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
