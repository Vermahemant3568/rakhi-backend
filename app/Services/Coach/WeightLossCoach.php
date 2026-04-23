<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class WeightLossCoach extends BaseCoach
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
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $ctx     = [];

        // ── Health conditions affecting weight loss ───────────────
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $ctx['has_pcos']      = str_contains($healthCond, 'pcos') || str_contains($lower, 'pcos');
        $ctx['has_thyroid']   = str_contains($healthCond, 'thyroid') || str_contains($lower, 'thyroid');
        $ctx['has_diabetes']  = str_contains($healthCond, 'diabet') || str_contains($lower, 'diabet');
        $ctx['has_bp']        = str_contains($healthCond, 'blood pressure') || str_contains($lower, 'hypertension');
        $ctx['has_knee_pain'] = preg_match('/\b(knee|ghutna|joint pain|knee pain)\b/i', $message);

        // ── Goal specifics ────────────────────────────────────────
        $ctx['target_weight']  = $memory['main_goal'] ?? null;
        $ctx['current_weight'] = $user->weight ?? null;
        $ctx['height']         = $user->height ?? null;
        $ctx['age']            = $user->getAge() > 0 ? $user->getAge() : null;
        $ctx['gender']         = $user->gender ?? null;
        $ctx['bmi']            = ($user->weight && $user->height)
            ? round($user->weight / (($user->height / 100) ** 2), 1)
            : null;

        // ── Diet preference ───────────────────────────────────────
        $pref = strtolower($memory['food_preference'] ?? $user->diet_preference ?? '');
        $ctx['is_veg']     = str_contains($pref, 'veg') && !str_contains($pref, 'non');
        $ctx['is_non_veg'] = str_contains($pref, 'non');
        $ctx['is_vegan']   = str_contains($pref, 'vegan');

        // ── Activity level ────────────────────────────────────────
        $ctx['activity']   = $memory['activity_level'] ?? $user->activity_level ?? null;
        $ctx['diet_habit'] = $memory['diet_habit']     ?? null;
        $ctx['lifestyle']  = $memory['lifestyle']      ?? null;
        $ctx['challenges'] = $memory['challenges']     ?? null;
        $ctx['stress']     = $memory['stress_level']   ?? $user->stress_level ?? null;
        $ctx['sleep']      = $memory['sleep_pattern']  ?? null;

        // ── Topic signals ─────────────────────────────────────────
        $ctx['asking_about_diet']       = preg_match('/\b(eat|food|diet|meal|calorie|what to eat|khana|kya khana)\b/i', $message);
        $ctx['asking_about_exercise']   = preg_match('/\b(exercise|workout|walk|gym|yoga|cardio|steps|movement)\b/i', $message);
        $ctx['asking_about_plateau']    = preg_match('/\b(plateau|stuck|not losing|same weight|no progress|weight nahi|ruk gaya)\b/i', $message);
        $ctx['asking_about_fasting']    = preg_match('/\b(fast|fasting|intermittent|IF|16:8|skip meal|vrat)\b/i', $message);
        $ctx['asking_about_cheat']      = preg_match('/\b(cheat|cheat meal|cheat day|treat|festival|wedding|party|function)\b/i', $message);
        $ctx['asking_about_supplements']= preg_match('/\b(supplement|fat burner|green tea|apple cider|protein|whey|tablet)\b/i', $message);
        $ctx['emotional_eating']        = preg_match('/\b(stress eat|bored eat|emotional|craving|binge|can.t stop|raat ko)\b/i', $message);
        $ctx['asking_about_plan']       = preg_match('/\b(plan|routine|schedule|program|what should i do|kya karoon)\b/i', $message);
        $ctx['asking_about_speed']      = preg_match('/\b(fast|quick|how fast|kitne din|how many days|how long|speed up|jaldi)\b/i', $message);
        $ctx['asking_about_belly']      = preg_match('/\b(belly|tummy|stomach|pet|waist|love handles|belly fat)\b/i', $message);

        return $ctx;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        $lines[] = 'You are a weight loss coach. Weight loss is about sustainable lifestyle change — not crash diets or extreme exercise. Always be encouraging, realistic, and practical. Reference the user\'s specific situation from memory.';

        // ── BMI context ───────────────────────────────────────────
        if ($ctx['bmi']) {
            $bmiNote = "User BMI: {$ctx['bmi']}. ";
            $bmiNote .= match(true) {
                $ctx['bmi'] < 18.5 => 'Underweight — weight loss is NOT appropriate. Focus on healthy weight maintenance.',
                $ctx['bmi'] < 25   => 'Healthy BMI — focus on body composition (reduce fat, build muscle) rather than scale weight.',
                $ctx['bmi'] < 30   => 'Overweight — realistic target: 0.5-1 kg/week loss. Diet is 80% of the result.',
                default            => 'Obese — even 5-10% weight loss significantly improves health markers. Start with small sustainable changes.',
            };
            $lines[] = $bmiNote;
        }

        // ── Condition-specific weight loss guidance ───────────────
        if ($ctx['has_pcos']) {
            $lines[] = 'PCOS and weight loss: insulin resistance makes weight loss harder in PCOS. Low GI diet is essential — avoid refined carbs and sugar. Strength training 3x/week improves insulin sensitivity more than cardio alone. Even 5-10% weight loss significantly improves PCOS symptoms. Inositol supplement may help — suggest consulting doctor. Do NOT suggest very low calorie diets — they worsen hormonal imbalance.';
        }

        if ($ctx['has_thyroid']) {
            $lines[] = 'Thyroid and weight loss: hypothyroidism slows metabolism — weight loss is harder and slower. If TSH is not controlled, weight loss will be very difficult regardless of diet. Ask if thyroid levels are currently in range. Avoid very low calorie diets — they further suppress thyroid function. Focus on consistent moderate deficit, strength training, and adequate sleep.';
        }

        if ($ctx['has_diabetes']) {
            $lines[] = 'Diabetes and weight loss: weight loss improves insulin sensitivity significantly — even 5-7% loss can reduce medication needs. Low GI diet is doubly important. Avoid skipping meals — causes blood sugar drops. Strength training is especially beneficial. Never suggest very low calorie diets without medical supervision.';
        }

        if ($ctx['has_knee_pain']) {
            $lines[] = 'Knee pain: avoid high-impact exercise (running, jumping). Safe options: swimming, cycling, walking on flat surface, water aerobics, seated exercises. Weight loss itself will reduce knee pain — every 1 kg lost reduces knee load by 4 kg. Strengthen quads and glutes to protect knees.';
        }

        // ── Core weight loss principles ───────────────────────────
        $lines[] = 'Weight loss fundamentals: calorie deficit is necessary but not sufficient. Hormones (insulin, cortisol, thyroid, leptin), sleep, stress, and gut health all affect weight. A 300-500 kcal daily deficit is sustainable. 1 kg fat = 7700 kcal deficit. Realistic rate: 0.5-1 kg/week. Faster loss = muscle loss + rebound.';

        // ── Diet guidance ─────────────────────────────────────────
        if ($ctx['asking_about_diet']) {
            $dietNote = 'Weight loss diet: high protein (1.2-1.6g/kg) preserves muscle during deficit. High fibre (vegetables, dal, whole grains) keeps you full. Reduce refined carbs (maida, white rice large portions, sugar, packaged food). Do NOT eliminate carbs — reduce and choose better quality. ';
            if ($ctx['is_veg']) {
                $dietNote .= 'Vegetarian protein: dal, rajma, chana, paneer (limited — high calorie), curd, soya chunks, tofu, eggs if eggetarian.';
            } else {
                $dietNote .= 'Protein sources: chicken breast (grilled/baked), fish, eggs, dal, paneer (limited).';
            }
            if ($ctx['diet_habit']) {
                $dietNote .= " Known diet habit: {$ctx['diet_habit']}.";
            }
            $lines[] = $dietNote;
        }

        // ── Exercise guidance ─────────────────────────────────────
        if ($ctx['asking_about_exercise']) {
            $exNote = 'Weight loss exercise: combination of strength training (3x/week) + cardio (150 min/week) is optimal. Strength training preserves muscle during deficit and boosts metabolism. Cardio burns calories. Walking after meals (10-15 min) is highly effective and sustainable. ';
            $activityLevel = strtolower((string)($ctx['activity'] ?? ''));
            if (str_contains($activityLevel, 'sedentary') || str_contains($activityLevel, 'light') || empty($activityLevel)) {
                $exNote .= 'Start with: 30-min walk daily + 2x/week bodyweight exercises. Build gradually.';
            }
            $lines[] = $exNote;
        }

        // ── Plateau handling ──────────────────────────────────────
        if ($ctx['asking_about_plateau']) {
            $lines[] = 'Weight loss plateau: body adapts to calorie deficit after 4-6 weeks. Solutions: 1) Recalculate TDEE (weight has changed, so calorie needs have changed). 2) Increase protein. 3) Add or change exercise type. 4) Take a 1-2 week diet break at maintenance calories (resets leptin). 5) Check sleep and stress — both cause cortisol which blocks fat loss. 6) Measure body composition, not just scale weight — muscle gain can mask fat loss.';
        }

        // ── Fasting guidance ─────────────────────────────────────
        if ($ctx['asking_about_fasting']) {
            $fastNote = 'Intermittent fasting for weight loss: 16:8 (eat 10am-6pm or 12pm-8pm) is effective for many people. Works by naturally reducing calorie intake. NOT recommended for: diabetics on medication (hypoglycemia risk), PCOS (can worsen cortisol), pregnant/breastfeeding women. ';
            if ($ctx['has_diabetes'] || $ctx['has_pcos']) {
                $fastNote .= 'Given their condition, suggest consulting doctor before starting IF.';
            }
            $lines[] = $fastNote;
        }

        // ── Cheat meals ───────────────────────────────────────────
        if ($ctx['asking_about_cheat']) {
            $lines[] = 'Cheat meals: one planned cheat meal per week is fine and actually helps — it resets leptin, prevents binge eating, and makes the diet sustainable. The key word is PLANNED — not a cheat day (too much damage). At festivals/weddings: eat a protein-rich meal before going, choose grilled over fried, take small portions of everything rather than large portions of a few things.';
        }

        // ── Supplements ───────────────────────────────────────────
        if ($ctx['asking_about_supplements']) {
            $lines[] = 'Weight loss supplements: most are ineffective or unsafe. What actually works: protein powder (helps hit protein target), green tea (modest effect, 50-100 kcal/day), apple cider vinegar (very modest effect on blood sugar). Fat burners: mostly stimulants with side effects — not recommended. Focus on diet and exercise first.';
        }

        // ── Emotional eating ──────────────────────────────────────
        if ($ctx['emotional_eating']) {
            $lines[] = 'Emotional/stress eating: acknowledge this with empathy — it is extremely common. The food is not the problem — the emotion is. Identify triggers (stress, boredom, loneliness, habit). Practical tools: 10-min delay rule (wait 10 min before eating when not hungry), drink water first, short walk, identify the emotion and name it. Keep healthy snacks available for when cravings hit.';
        }

        // ── Belly fat ─────────────────────────────────────────────
        if ($ctx['asking_about_belly']) {
            $lines[] = 'Belly fat: spot reduction is a myth — you cannot target belly fat with crunches. Belly fat (visceral fat) is reduced through overall fat loss. However, belly fat responds well to: reducing refined carbs and sugar, reducing alcohol, managing cortisol (stress and sleep), and strength training. High cortisol specifically deposits fat around the abdomen.';
        }

        // ── Speed of weight loss ──────────────────────────────────
        if ($ctx['asking_about_speed']) {
            $lines[] = 'How fast can you lose weight: 0.5-1 kg/week is sustainable and preserves muscle. Faster loss (>1 kg/week) causes muscle loss, nutrient deficiencies, and almost always leads to rebound. The goal is to lose fat and keep it off — not just lose weight quickly. Slow and steady wins this race.';
        }

        // ── Plan request ──────────────────────────────────────────
        if ($ctx['asking_about_plan']) {
            $planNote = 'Weight loss plan framework: 1) Calculate TDEE and set 300-500 kcal deficit. 2) High protein diet (1.2-1.6g/kg). 3) Strength training 3x/week. 4) 150 min cardio/week (walking counts). 5) 7-8 hours sleep (poor sleep = weight gain). 6) Stress management (cortisol = belly fat). ';
            if ($ctx['lifestyle']) {
                $planNote .= "User lifestyle: {$ctx['lifestyle']} — fit plan around their actual schedule.";
            }
            $lines[] = $planNote;
        }

        // ── Sleep and stress impact ───────────────────────────────
        if ($ctx['sleep'] && str_contains(strtolower($ctx['sleep']), 'poor') || $ctx['sleep'] && str_contains(strtolower($ctx['sleep']), '5') || $ctx['sleep'] && str_contains(strtolower($ctx['sleep']), '4')) {
            $lines[] = "Known sleep pattern: {$ctx['sleep']}. Poor sleep increases ghrelin (hunger hormone) and decreases leptin (fullness hormone) — causing 300-500 extra calories consumed per day. Fixing sleep is as important as diet for weight loss.";
        }

        if ($ctx['stress'] && (str_contains(strtolower((string)$ctx['stress']), 'high') || str_contains(strtolower((string)$ctx['stress']), 'stress'))) {
            $lines[] = "Known stress level: {$ctx['stress']}. Chronic stress raises cortisol which promotes fat storage (especially belly fat) and causes cravings for high-calorie foods. Stress management is a weight loss strategy.";
        }

        // ── Gender specific ───────────────────────────────────────
        if ($ctx['gender'] === 'female') {
            $lines[] = 'Female weight loss: hormonal fluctuations affect weight — scale can vary 1-3 kg across the menstrual cycle (water retention). Do not panic at scale fluctuations. Strength training is especially important for women — builds bone density and boosts metabolism. Women lose weight slightly slower than men due to lower muscle mass and different hormonal profile.';
        }

        // ── Age specific ──────────────────────────────────────────
        if ($ctx['age'] && $ctx['age'] >= 40) {
            $lines[] = 'User 40+: metabolism slows ~2-3% per decade after 30. Muscle loss accelerates. Strength training is the most important intervention — preserves muscle and keeps metabolism higher. Hormonal changes (perimenopause, andropause) affect fat distribution. More patience required — results take longer but are achievable.';
        }

        // ── Challenges ────────────────────────────────────────────
        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these directly — do not give advice that ignores their real barriers.";
        }

        $block = "WEIGHT LOSS COACHING CONTEXT (use this intelligence to give specific, personalised advice — never generic):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
