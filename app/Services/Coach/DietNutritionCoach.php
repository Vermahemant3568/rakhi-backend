<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class DietNutritionCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $dietContext     = $this->buildDietContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $dietContext);

        return parent::respond($user, $enrichedMessage, $sessionId);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTEXT BUILDER
    // ─────────────────────────────────────────────────────────────

    private function buildDietContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // ── Diet preference ──────────────────────────────────────
        $storedPref = strtolower($memory['food_preference'] ?? $user->diet_preference ?? '');

        $context['is_veg']        = str_contains($storedPref, 'veg')    && !str_contains($storedPref, 'non');
        $context['is_non_veg']    = str_contains($storedPref, 'non');
        $context['is_vegan']      = str_contains($storedPref, 'vegan');
        $context['is_eggetarian'] = str_contains($storedPref, 'egg');

        // ── Goal detection ───────────────────────────────────────
        $goals      = strtolower($user->goals->pluck('name')->join(' '));
        $mainGoal   = strtolower($memory['main_goal'] ?? '');
        $allGoals   = $goals . ' ' . $mainGoal;

        $context['goal_weight_loss']   = str_contains($allGoals, 'weight') || str_contains($allGoals, 'fat') || str_contains($allGoals, 'slim');
        $context['goal_muscle']        = str_contains($allGoals, 'muscle') || str_contains($allGoals, 'build') || str_contains($allGoals, 'strength');
        $context['goal_energy']        = str_contains($allGoals, 'energy') || str_contains($allGoals, 'fatigue') || str_contains($allGoals, 'tired');
        $context['goal_gut']           = str_contains($allGoals, 'gut') || str_contains($allGoals, 'digest') || str_contains($allGoals, 'bloat') || str_contains($allGoals, 'ibs');
        $context['goal_diabetes']      = str_contains($allGoals, 'diabet') || str_contains($allGoals, 'sugar');
        $context['goal_pcos']          = str_contains($allGoals, 'pcos') || str_contains($allGoals, 'pcod') || str_contains($allGoals, 'hormone');
        $context['goal_general']       = !$context['goal_weight_loss'] && !$context['goal_muscle'] && !$context['goal_energy'];

        // ── Topic signals from current message ───────────────────
        $context['asking_about_calories']    = preg_match('/\b(calorie|kcal|caloric|how much to eat|how many calories|deficit|surplus)\b/i', $message);
        $context['asking_about_protein']     = preg_match('/\b(protein|daal|dal|paneer|egg|chicken|soya|tofu|legume|lentil|whey|amino)\b/i', $message);
        $context['asking_about_carbs']       = preg_match('/\b(carb|rice|roti|bread|wheat|maida|sugar|starch|grain|atta|chapati)\b/i', $message);
        $context['asking_about_fat']         = preg_match('/\b(fat|ghee|oil|butter|fried|oily|omega|avocado|nuts|seeds)\b/i', $message);
        $context['asking_about_meal_plan']   = preg_match('/\b(meal plan|diet plan|what to eat|weekly plan|daily plan|plan for me|plan bana)\b/i', $message);
        $context['asking_about_breakfast']   = preg_match('/\b(breakfast|subah|morning meal|nashta|nasta)\b/i', $message);
        $context['asking_about_lunch']       = preg_match('/\b(lunch|dopahar|afternoon meal|daal chawal|dal rice)\b/i', $message);
        $context['asking_about_dinner']      = preg_match('/\b(dinner|raat ka khana|night meal|supper)\b/i', $message);
        $context['asking_about_snacks']      = preg_match('/\b(snack|munchies|evening snack|biscuit|namkeen|chips|hunger between)\b/i', $message);
        $context['asking_about_water']       = preg_match('/\b(water|hydrat|pani|drink|fluid)\b/i', $message);
        $context['asking_about_supplements'] = preg_match('/\b(supplement|vitamin|mineral|iron|calcium|b12|d3|zinc|magnesium|omega 3|multivitamin)\b/i', $message);
        $context['asking_about_fasting']     = preg_match('/\b(fast|fasting|intermittent|IF|16:8|ramadan|navratri|vrat|upvas|skip meal)\b/i', $message);
        $context['asking_about_outside_food']= preg_match('/\b(outside|restaurant|order|zomato|swiggy|canteen|office food|street food|junk)\b/i', $message);
        $context['asking_about_weight']      = preg_match('/\b(weight|kg|kilo|bmi|fat|slim|thin|mota|patla|vajan)\b/i', $message);
        $context['asking_about_timing']      = preg_match('/\b(when|timing|time|before|after|gap|interval|how often|meal time)\b/i', $message);

        // ── Deficiency / symptom signals ─────────────────────────
        $context['possible_iron_deficiency']    = preg_match('/\b(tired|fatigue|pale|anaemia|anemia|iron|weak|breathless|hair fall)\b/i', $message);
        $context['possible_protein_deficiency'] = preg_match('/\b(hair loss|hair fall|weak nails|muscle loss|slow recovery|always hungry)\b/i', $message);
        $context['possible_vitamin_d']          = preg_match('/\b(bone pain|joint pain|d3|vitamin d|sunlight|calcium|weak bones)\b/i', $message);
        $context['possible_b12']                = preg_match('/\b(b12|numbness|tingling|memory|foggy|nerve|vegan|vegetarian)\b/i', $message);
        $context['digestive_issues']            = preg_match('/\b(bloat|gas|acidity|constipat|loose stool|ibs|gut|digest|stomach|acid reflux|heartburn)\b/i', $message);

        // ── Eating pattern signals ────────────────────────────────
        $context['skips_meals']       = preg_match('/\b(skip|no time|busy|forget to eat|miss meal|don.t eat|dont eat)\b/i', $message);
        $context['eats_late_night']   = preg_match('/\b(late night|midnight|2am|3am|raat ko|late khana|night eating)\b/i', $message);
        $context['emotional_eating']  = preg_match('/\b(stress eat|bored eat|emotional|crave|craving|binge|can.t stop eating)\b/i', $message);
        $context['overeating']        = preg_match('/\b(overeat|too much|can.t control|portion|binge|eat a lot|bahut khata|bahut khati)\b/i', $message);

        // ── Memory context ────────────────────────────────────────
        $context['diet_habit']    = $memory['diet_habit']    ?? null;
        $context['meal_timing']   = $memory['diet_timing']   ?? null;
        $context['activity']      = $memory['activity_level'] ?? $user->activity_level ?? null;
        $context['challenges']    = $memory['challenges']    ?? null;
        $context['lifestyle']     = $memory['lifestyle']     ?? null;
        $context['health_cond']   = $memory['health_condition'] ?? null;

        // ── User physical stats ───────────────────────────────────
        $context['weight']  = $user->weight  ?? null;
        $context['height']  = $user->height  ?? null;
        $context['age']     = $user->getAge() > 0 ? $user->getAge() : null;
        $context['gender']  = $user->gender  ?? null;

        return $context;
    }

    // ─────────────────────────────────────────────────────────────
    // MESSAGE ENRICHER
    // ─────────────────────────────────────────────────────────────

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // ── Diet preference baseline ─────────────────────────────
        if ($ctx['is_vegan']) {
            $lines[] = 'User is vegan. All suggestions must be 100% plant-based. No dairy, no eggs, no meat. Emphasise plant protein sources: tofu, tempeh, legumes, seeds.';
        } elseif ($ctx['is_veg']) {
            $lines[] = 'User is vegetarian. No meat or fish. Good protein sources: paneer, dal, rajma, chana, curd, milk, soya, tofu, eggs if eggetarian.';
        } elseif ($ctx['is_non_veg']) {
            $lines[] = 'User eats non-veg. Can include chicken, fish, eggs. Suggest lean proteins. Avoid red meat overuse.';
        }

        // ── Goal-based nutrition strategy ────────────────────────
        if ($ctx['goal_weight_loss']) {
            $lines[] = 'Goal: weight loss. Strategy: moderate calorie deficit (300-500 kcal below TDEE), high protein to preserve muscle, high fibre to stay full, reduce refined carbs and sugar. Avoid crash diets.';
        }
        if ($ctx['goal_muscle']) {
            $lines[] = 'Goal: muscle building. Strategy: calorie surplus (200-300 kcal above TDEE), high protein (1.6-2.2g per kg body weight), distribute protein across all meals, post-workout nutrition matters.';
        }
        if ($ctx['goal_energy']) {
            $lines[] = 'Goal: boost energy. Strategy: balanced meals every 3-4 hours, complex carbs for sustained energy, iron and B12 check if always tired, avoid sugar spikes and crashes, stay hydrated.';
        }
        if ($ctx['goal_gut']) {
            $lines[] = 'Goal: gut health. Strategy: high fibre foods (vegetables, fruits, whole grains), probiotic foods (curd, buttermilk, idli, dosa), avoid processed food, eat slowly, stay hydrated.';
        }
        if ($ctx['goal_diabetes']) {
            $lines[] = 'User has diabetes-related nutrition goal. Focus on low glycemic index foods, portion control, avoid refined carbs and sugar, eat every 3-4 hours to prevent spikes.';
        }
        if ($ctx['goal_pcos']) {
            $lines[] = 'User has PCOS-related nutrition goal. Focus on anti-inflammatory foods, low GI carbs, high protein, reduce sugar and processed food, include omega-3 rich foods.';
        }

        // ── Physical stats for calorie/macro calculation ─────────
        if ($ctx['weight'] && $ctx['height'] && $ctx['age']) {
            $bmr = $ctx['gender'] === 'female'
                ? 447.6 + (9.25 * $ctx['weight']) + (3.10 * $ctx['height']) - (4.33 * $ctx['age'])
                : 88.4  + (13.4 * $ctx['weight']) + (4.8  * $ctx['height']) - (5.68 * $ctx['age']);

            $activityMultiplier = match(true) {
                str_contains((string)($ctx['activity'] ?? ''), 'sedentary')  => 1.2,
                str_contains((string)($ctx['activity'] ?? ''), 'light')      => 1.375,
                str_contains((string)($ctx['activity'] ?? ''), 'moderate')   => 1.55,
                str_contains((string)($ctx['activity'] ?? ''), 'active')     => 1.725,
                str_contains((string)($ctx['activity'] ?? ''), 'very_active')=> 1.9,
                default => 1.4,
            };

            $tdee = round($bmr * $activityMultiplier);
            $lines[] = "User stats: weight={$ctx['weight']}kg, height={$ctx['height']}cm, age={$ctx['age']}. Estimated TDEE ≈ {$tdee} kcal/day. Use this for calorie guidance if asked.";
        }

        // ── Topic-specific intelligence ───────────────────────────
        if ($ctx['asking_about_calories']) {
            $lines[] = 'When discussing calories: explain TDEE vs BMR simply. For weight loss suggest 300-500 kcal deficit. For muscle gain suggest 200-300 surplus. Never recommend below 1200 kcal for women or 1500 for men.';
        }

        if ($ctx['asking_about_protein']) {
            $proteinNote = 'Protein guidance: general health = 0.8g/kg, weight loss = 1.2-1.6g/kg, muscle building = 1.6-2.2g/kg. ';
            if ($ctx['is_veg']) {
                $proteinNote .= 'Vegetarian sources: dal (9g/100g cooked), paneer (18g/100g), curd (3.5g/100g), rajma (9g/100g cooked), soya chunks (52g/100g dry), tofu (8g/100g).';
            } else {
                $proteinNote .= 'Sources: chicken breast (31g/100g), eggs (6g each), fish (22-25g/100g), dal, paneer, curd.';
            }
            $lines[] = $proteinNote;
        }

        if ($ctx['asking_about_carbs']) {
            $lines[] = 'Carb guidance: prefer complex carbs (brown rice, whole wheat roti, oats, sweet potato, millets like jowar/bajra/ragi). Avoid maida, white bread, sugary drinks. Portion: 1 medium roti ≈ 15g carbs, 1 cup cooked rice ≈ 45g carbs.';
        }

        if ($ctx['asking_about_fat']) {
            $lines[] = 'Fat guidance: healthy fats are essential. Include ghee (1 tsp/day), nuts (handful), seeds (flax, chia, sunflower), cold-pressed oils. Avoid trans fats, vanaspati, excess fried food. Fat does NOT make you fat — excess calories do.';
        }

        if ($ctx['asking_about_meal_plan']) {
            $mealNote = 'When suggesting a meal plan: structure it as breakfast, mid-morning snack, lunch, evening snack, dinner. ';
            if ($ctx['diet_habit']) {
                $mealNote .= "Known eating habit: {$ctx['diet_habit']}. ";
            }
            if ($ctx['challenges']) {
                $mealNote .= "Known challenge: {$ctx['challenges']}. ";
            }
            $mealNote .= 'Keep it practical, Indian, and realistic. Do not suggest exotic or expensive foods.';
            $lines[] = $mealNote;
        }

        if ($ctx['asking_about_breakfast']) {
            $lines[] = 'Breakfast should be eaten within 1-2 hours of waking. Good Indian options: poha, upma, idli-sambar, oats, eggs, moong dal chilla, sprouts. Avoid skipping — it sets metabolism for the day.';
        }

        if ($ctx['asking_about_lunch']) {
            $lines[] = 'Lunch should be the largest meal. Ideal plate: 50% vegetables, 25% protein (dal/paneer/chicken), 25% complex carbs (roti/rice). Include a small salad or raita.';
        }

        if ($ctx['asking_about_dinner']) {
            $lines[] = 'Dinner should be lighter than lunch. Eat at least 2 hours before sleep. Good options: khichdi, dal soup, sabzi with 1-2 rotis, grilled protein with vegetables. Avoid heavy fried food at night.';
        }

        if ($ctx['asking_about_snacks']) {
            $lines[] = 'Healthy Indian snack options: roasted chana, makhana, fruit with peanut butter, curd, handful of nuts, sprouts chaat, cucumber with hummus. Avoid biscuits, namkeen, chips — high in sodium and refined carbs.';
        }

        if ($ctx['asking_about_water']) {
            $lines[] = 'Hydration: minimum 2.5-3 litres/day. More if active or in hot weather. Start day with 2 glasses of water. Drink before meals (not during — dilutes digestive enzymes). Coconut water, buttermilk, nimbu pani are good options.';
        }

        if ($ctx['asking_about_fasting']) {
            $lines[] = 'Intermittent fasting: 16:8 is most practical (eat between 10am-6pm or 12pm-8pm). Benefits: insulin sensitivity, fat loss. Not recommended for: pregnant women, underweight, history of eating disorders. During religious fasts: focus on protein and complex carbs in eating window.';
        }

        if ($ctx['asking_about_outside_food']) {
            $outsideNote = 'Eating outside tips: choose grilled/tandoor over fried, ask for less oil, avoid creamy gravies, choose dal/sabzi over paneer butter masala, opt for roti over naan, avoid sugary drinks. ';
            if ($ctx['diet_habit'] && str_contains(strtolower($ctx['diet_habit']), 'outside')) {
                $outsideNote .= "Since user eats outside regularly, focus on making the best choices from available options rather than asking them to cook.";
            }
            $lines[] = $outsideNote;
        }

        if ($ctx['asking_about_timing']) {
            $lines[] = 'Meal timing: eat every 3-4 hours to maintain blood sugar and metabolism. Do not go more than 5 hours without eating. Largest meal at lunch, lightest at dinner. Post-workout: eat protein within 30-45 minutes.';
        }

        // ── Supplement / deficiency intelligence ─────────────────
        if ($ctx['asking_about_supplements']) {
            $suppNote = 'Common deficiencies in Indians: Vitamin D3 (most Indians are deficient — 1000-2000 IU/day), B12 (especially vegetarians — 500mcg/day), Iron (especially women), Calcium, Omega-3. ';
            if ($ctx['is_veg'] || $ctx['is_vegan']) {
                $suppNote .= 'Vegetarians/vegans: B12 supplementation is essential (cannot get from plant food). Also consider Omega-3 algae oil.';
            }
            $lines[] = $suppNote;
        }

        if ($ctx['possible_iron_deficiency']) {
            $lines[] = 'Possible iron deficiency signal. Iron-rich Indian foods: spinach, methi, rajma, chana, jaggery, sesame seeds, pomegranate. Pair with Vitamin C (lemon, amla) to improve absorption. Avoid tea/coffee with meals — blocks iron absorption.';
        }

        if ($ctx['possible_protein_deficiency']) {
            $lines[] = 'Possible protein deficiency signal (hair fall, muscle loss, slow recovery). Suggest increasing protein at every meal. Check if user is meeting daily protein target.';
        }

        if ($ctx['possible_vitamin_d']) {
            $lines[] = 'Possible Vitamin D deficiency signal. Suggest: 15-20 min morning sunlight, D3 supplement (1000-2000 IU), dietary sources: egg yolk, fatty fish, fortified milk. D3 deficiency is extremely common in India.';
        }

        if ($ctx['possible_b12']) {
            $lines[] = 'Possible B12 deficiency signal. B12 is found only in animal products. Vegetarians/vegans must supplement. Symptoms: fatigue, numbness, memory issues, nerve problems. Suggest B12 test if not done recently.';
        }

        if ($ctx['digestive_issues']) {
            $lines[] = 'Digestive issues detected. Suggest: eat slowly and chew well, avoid cold water with meals, include probiotic foods (curd, buttermilk, idli, dosa, kanji), reduce spicy and oily food, increase fibre gradually, stay hydrated. Identify trigger foods.';
        }

        // ── Eating pattern issues ─────────────────────────────────
        if ($ctx['skips_meals']) {
            $lines[] = 'User skips meals due to busy schedule. Suggest: keep quick healthy options ready (roasted chana, fruits, curd, boiled eggs, nuts). Skipping meals slows metabolism and causes overeating later. Even a small snack is better than nothing.';
        }

        if ($ctx['eats_late_night']) {
            $lines[] = 'User eats late at night. This disrupts metabolism and sleep quality. Suggest: have a light dinner by 8-9pm, if hungry later have a small protein snack (curd, handful of nuts). Avoid carb-heavy food after 9pm.';
        }

        if ($ctx['emotional_eating']) {
            $lines[] = 'User shows signs of emotional or stress eating. Acknowledge this with empathy first. Suggest: identify triggers, keep healthy snacks available, drink water before reaching for food, short walk or breathing exercise when craving hits. Do not shame them.';
        }

        if ($ctx['overeating']) {
            $lines[] = 'User struggles with portion control or overeating. Suggest: use smaller plates, eat slowly (20 min for fullness signal), start meals with salad or soup, avoid eating from packets, do not eat while watching TV/phone.';
        }

        // ── Known context from memory ─────────────────────────────
        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Tailor suggestions to fit their schedule.";
        }

        if ($ctx['health_cond']) {
            $lines[] = "Known health condition: {$ctx['health_cond']}. Ensure all diet advice is safe for this condition.";
        }

        // ── Build final enriched block ────────────────────────────
        $block = "DIET & NUTRITION COACHING CONTEXT (use this intelligence naturally — do not list it, weave it into your response):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
