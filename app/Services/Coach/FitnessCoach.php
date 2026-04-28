<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class FitnessCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId, string $inputMode = 'chat'): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $fitnessContext  = $this->buildFitnessContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $fitnessContext);

        return parent::respond($user, $enrichedMessage, $sessionId, $inputMode);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTEXT BUILDER
    // ─────────────────────────────────────────────────────────────

    private function buildFitnessContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // ── Fitness level detection ───────────────────────────────
        $activityStored = strtolower($memory['activity_level'] ?? $user->activity_level ?? '');

        $context['is_beginner']   = str_contains($activityStored, 'sedentary')
            || str_contains($activityStored, 'light')
            || preg_match('/\b(beginner|new to|just started|never exercised|no exercise|starting|shuru karna|pehli baar)\b/i', $message);

        $context['is_intermediate'] = str_contains($activityStored, 'moderate')
            || preg_match('/\b(intermediate|some experience|workout karta|workout karti|few months|6 months|1 year)\b/i', $message);

        $context['is_advanced']   = str_contains($activityStored, 'active')
            || str_contains($activityStored, 'very_active')
            || preg_match('/\b(advanced|athlete|years of training|competitive|bodybuilder|runner|marathon|powerlifter)\b/i', $message);

        // ── Fitness goal detection ────────────────────────────────
        $goals    = strtolower($user->goals->pluck('name')->join(' '));
        $mainGoal = strtolower($memory['main_goal'] ?? '');
        $allGoals = $goals . ' ' . $mainGoal . ' ' . $lower;

        $context['goal_weight_loss']  = preg_match('/\b(weight loss|lose weight|fat loss|slim|burn fat|calorie burn|vajan kam|mota|belly fat|tummy)\b/i', $allGoals);
        $context['goal_muscle']       = preg_match('/\b(muscle|build|bulk|strength|mass|gain|toned|lean muscle|bicep|chest|back)\b/i', $allGoals);
        $context['goal_endurance']    = preg_match('/\b(endurance|stamina|cardio|run|marathon|cycling|swimming|breathless|fitness level)\b/i', $allGoals);
        $context['goal_flexibility']  = preg_match('/\b(flexible|flexibility|stretch|yoga|mobility|stiff|tight muscles|posture)\b/i', $allGoals);
        $context['goal_general']      = !$context['goal_weight_loss'] && !$context['goal_muscle'] && !$context['goal_endurance'];

        // ── Workout location ──────────────────────────────────────
        $context['has_gym']      = preg_match('/\b(gym|equipment|machine|dumbbell|barbell|bench|treadmill|cable|rack)\b/i', $message);
        $context['home_workout'] = preg_match('/\b(home|ghar|no gym|without equipment|bodyweight|no equipment|home workout)\b/i', $message);
        $context['outdoor']      = preg_match('/\b(outdoor|park|run|walk|jogging|cycling|outside|road|ground)\b/i', $message);

        // ── Workout type signals ──────────────────────────────────
        $context['asking_about_cardio']     = preg_match('/\b(cardio|running|jogging|cycling|swimming|aerobic|hiit|zumba|dance|steps|treadmill)\b/i', $message);
        $context['asking_about_strength']   = preg_match('/\b(strength|weight training|lifting|dumbbell|barbell|resistance|push up|pull up|squat|deadlift|bench press)\b/i', $message);
        $context['asking_about_yoga']       = preg_match('/\b(yoga|asana|pranayama|surya namaskar|meditation|flexibility|stretch|pilates)\b/i', $message);
        $context['asking_about_walk']       = preg_match('/\b(walk|walking|steps|10000 steps|morning walk|evening walk|paidal)\b/i', $message);
        $context['asking_about_abs']        = preg_match('/\b(abs|core|belly|stomach|tummy|six pack|plank|crunch|sit up)\b/i', $message);
        $context['asking_about_plan']       = preg_match('/\b(plan|routine|schedule|program|workout plan|exercise plan|weekly plan|plan bana)\b/i', $message);
        $context['asking_about_frequency']  = preg_match('/\b(how many days|how often|per week|daily|kitne din|frequency|times a week)\b/i', $message);
        $context['asking_about_duration']   = preg_match('/\b(how long|duration|minutes|hours|kitni der|time lagega|how much time)\b/i', $message);
        $context['asking_about_warm_up']    = preg_match('/\b(warm up|warmup|cool down|cooldown|stretching|before workout|after workout)\b/i', $message);
        $context['asking_about_recovery']   = preg_match('/\b(recovery|rest day|sore|soreness|muscle pain|DOMS|overtraining|rest|tired after)\b/i', $message);
        $context['asking_about_nutrition']  = preg_match('/\b(pre workout|post workout|protein|eat before|eat after|supplement|creatine|whey|bcaa)\b/i', $message);
        $context['asking_about_plateau']    = preg_match('/\b(plateau|stuck|no progress|not losing|not gaining|same weight|stopped working|results stopped)\b/i', $message);
        $context['asking_about_motivation'] = preg_match('/\b(motivat|lazy|no mood|skip workout|can.t workout|give up|quit|consistency|discipline)\b/i', $message);

        // ── Injury / limitation signals ───────────────────────────
        $context['has_knee_pain']    = preg_match('/\b(knee|ghutna|knee pain|knee injury|ACL|meniscus|patella)\b/i', $message);
        $context['has_back_pain']    = preg_match('/\b(back pain|lower back|spine|disc|sciatica|kamar dard|back injury|herniated)\b/i', $message);
        $context['has_shoulder_pain']= preg_match('/\b(shoulder|rotator cuff|shoulder pain|shoulder injury|frozen shoulder)\b/i', $message);
        $context['is_overweight']    = preg_match('/\b(overweight|obese|obesity|very heavy|100kg|90kg|80kg|bmi over|high bmi)\b/i', $message)
            || ($user->weight && $user->height && $this->calculateBMI($user->weight, $user->height) >= 30);
        $context['is_pregnant']      = preg_match('/\b(pregnant|pregnancy|trimester|garbhvati|expecting)\b/i', $message);
        $context['post_surgery']     = preg_match('/\b(surgery|operation|post op|recovering|rehabilitation|rehab|recently operated)\b/i', $message);

        // ── Health conditions affecting exercise ──────────────────
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $context['has_diabetes']  = str_contains($healthCond, 'diabet') || str_contains($lower, 'diabet');
        $context['has_bp']        = str_contains($healthCond, 'blood pressure') || str_contains($healthCond, 'hypertension') || str_contains($lower, 'bp') || str_contains($lower, 'hypertension');
        $context['has_heart']     = str_contains($healthCond, 'heart') || str_contains($lower, 'heart condition') || str_contains($lower, 'cardiac');
        $context['has_pcos']      = str_contains($healthCond, 'pcos') || str_contains($healthCond, 'pcod') || str_contains($lower, 'pcos');
        $context['has_thyroid']   = str_contains($healthCond, 'thyroid') || str_contains($lower, 'thyroid');
        $context['has_asthma']    = str_contains($healthCond, 'asthma') || str_contains($lower, 'asthma') || str_contains($lower, 'breathing problem');

        // ── Diet preference for nutrition advice ──────────────────
        $storedPref = strtolower($memory['food_preference'] ?? $user->diet_preference ?? '');
        $context['is_veg']     = str_contains($storedPref, 'veg') && !str_contains($storedPref, 'non');
        $context['is_non_veg'] = str_contains($storedPref, 'non');
        $context['is_vegan']   = str_contains($storedPref, 'vegan');

        // ── Memory context ────────────────────────────────────────
        $context['activity']    = $memory['activity_level'] ?? $user->activity_level ?? null;
        $context['lifestyle']   = $memory['lifestyle']      ?? null;
        $context['challenges']  = $memory['challenges']     ?? null;
        $context['sleep']       = $memory['sleep_pattern']  ?? null;
        $context['stress']      = $memory['stress_level']   ?? $user->stress_level ?? null;

        // ── Physical stats ────────────────────────────────────────
        $context['weight'] = $user->weight ?? null;
        $context['height'] = $user->height ?? null;
        $context['age']    = $user->getAge() > 0 ? $user->getAge() : null;
        $context['gender'] = $user->gender ?? null;
        $context['bmi']    = ($user->weight && $user->height)
            ? $this->calculateBMI($user->weight, $user->height)
            : null;

        return $context;
    }

    // ─────────────────────────────────────────────────────────────
    // MESSAGE ENRICHER
    // ─────────────────────────────────────────────────────────────

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // ── Core framing ──────────────────────────────────────────
        $lines[] = 'You are a fitness coach. Always tailor advice to the user\'s fitness level, goal, available equipment, and any injuries or health conditions. Never give one-size-fits-all workout advice.';

        // ── Fitness level baseline ────────────────────────────────
        if ($ctx['is_beginner']) {
            $lines[] = 'User is a BEGINNER. Start slow — 3 days/week, 20-30 min sessions. Focus on form over intensity. Bodyweight exercises first. Avoid complex movements. Build the habit before building the intensity. Soreness is normal in week 1-2 but should not be extreme pain.';
        } elseif ($ctx['is_intermediate']) {
            $lines[] = 'User has INTERMEDIATE fitness level. Can handle 4 days/week, 30-45 min sessions. Can introduce progressive overload, supersets, and split routines. Focus on consistency and gradual progression.';
        } elseif ($ctx['is_advanced']) {
            $lines[] = 'User is ADVANCED. Can handle 5-6 days/week, 45-60 min sessions. Periodisation, progressive overload, deload weeks, and sport-specific training are relevant. Recovery and nutrition are as important as training.';
        }

        // ── Goal-based training strategy ─────────────────────────
        if ($ctx['goal_weight_loss']) {
            $lines[] = 'Goal: fat loss. Strategy: combination of strength training (preserves muscle) + cardio (burns calories). Calorie deficit through diet is more effective than exercise alone — you cannot out-train a bad diet. Strength training 3x/week + 150 min moderate cardio/week. HIIT is time-efficient for fat loss.';
        }

        if ($ctx['goal_muscle']) {
            $lines[] = 'Goal: muscle building. Strategy: progressive overload is the key principle — gradually increase weight, reps, or sets over time. 3-4 sets of 8-12 reps per exercise. Compound movements first (squat, deadlift, bench, rows). Protein intake 1.6-2.2g/kg body weight. Rest 48 hours between same muscle groups.';
        }

        if ($ctx['goal_endurance']) {
            $lines[] = 'Goal: endurance/stamina. Strategy: progressive cardio — start with 20 min and add 10% distance/time per week. Zone 2 cardio (conversational pace) builds aerobic base. Include 1 interval session per week. Strength training 2x/week prevents injury and improves running economy.';
        }

        if ($ctx['goal_flexibility']) {
            $lines[] = 'Goal: flexibility/mobility. Strategy: dynamic stretching before workout, static stretching after. Yoga 3x/week is highly effective. Hold stretches 30-60 seconds. Focus on hip flexors, hamstrings, thoracic spine — most Indians are tight here from desk jobs. Consistency matters more than intensity.';
        }

        // ── Location-based workout guidance ──────────────────────
        if ($ctx['home_workout']) {
            $lines[] = 'User works out at HOME without equipment. Effective bodyweight exercises: push-ups (chest/triceps), squats (legs/glutes), lunges, plank (core), mountain climbers, burpees, glute bridges, pike push-ups (shoulders). Can use water bottles as light weights, chair for dips, wall for wall sits.';
        }

        if ($ctx['has_gym']) {
            $lines[] = 'User has GYM access. Prioritise compound movements: squat, deadlift, bench press, overhead press, barbell rows, pull-ups. These give maximum results per time invested. Machines are good for isolation and beginners learning movement patterns.';
        }

        if ($ctx['outdoor']) {
            $lines[] = 'User prefers OUTDOOR exercise. Options: brisk walking, jogging, cycling, park bodyweight circuits, stair climbing. Morning outdoor exercise also provides Vitamin D and improves mood. Suggest tracking steps/distance for progression.';
        }

        // ── Injury and limitation handling ────────────────────────
        if ($ctx['has_knee_pain']) {
            $lines[] = 'KNEE PAIN: Avoid deep squats, lunges, running on hard surfaces, leg press with heavy weight. Safe alternatives: swimming, cycling, seated leg extensions (light), straight leg raises, wall sits (shallow). Strengthen VMO (inner quad) and glutes to reduce knee stress. Suggest seeing a physiotherapist if pain is persistent.';
        }

        if ($ctx['has_back_pain']) {
            $lines[] = 'BACK PAIN: Avoid heavy deadlifts, sit-ups, leg raises, high-impact jumping. Safe alternatives: bird-dog, dead bug, glute bridges, cat-cow, swimming, walking. Core strengthening (not crunches — planks and bird-dogs) is the best long-term fix for lower back pain. Always maintain neutral spine.';
        }

        if ($ctx['has_shoulder_pain']) {
            $lines[] = 'SHOULDER PAIN: Avoid overhead pressing, upright rows, behind-neck exercises. Safe alternatives: cable rows, face pulls, band pull-aparts, lateral raises (light). Rotator cuff strengthening exercises are key. Suggest physiotherapy assessment for frozen shoulder or rotator cuff issues.';
        }

        if ($ctx['is_overweight']) {
            $lines[] = 'User is overweight/obese. Avoid high-impact exercises (running, jumping) initially — high joint stress risk. Start with: walking, swimming, cycling, water aerobics, chair exercises, resistance bands. Build base fitness for 4-6 weeks before adding impact. Even 10 min walks 3x/day is effective and sustainable.';
        }

        if ($ctx['is_pregnant']) {
            $lines[] = 'User is PREGNANT. Exercise is safe and beneficial during pregnancy but must be modified. Avoid: lying flat on back after 1st trimester, heavy lifting, contact sports, high-impact jumping, exercises that risk falling. Safe: walking, swimming, prenatal yoga, light resistance training. Always recommend consulting OB/GYN before starting.';
        }

        if ($ctx['post_surgery']) {
            $lines[] = 'User is POST-SURGERY or recovering. Do NOT suggest any exercise without knowing what surgery and how long ago. Ask first. Rehabilitation exercises must be guided by a physiotherapist. General advice: walking is usually safe early, but clearance from doctor is mandatory before any structured exercise.';
        }

        // ── Health condition exercise modifications ────────────────
        if ($ctx['has_diabetes']) {
            $lines[] = 'User has diabetes. Exercise lowers blood sugar — check levels before and after workout. Carry fast-acting sugar during exercise. Best time to exercise: 1-2 hours after a meal. Avoid exercise if fasting sugar >250 mg/dL. Both cardio and strength training improve insulin sensitivity.';
        }

        if ($ctx['has_bp']) {
            $lines[] = 'User has high blood pressure. Avoid heavy lifting with breath-holding (Valsalva manoeuvre). Avoid isometric exercises held for long. Safe: moderate cardio (walking, cycling, swimming), light-moderate strength training with proper breathing. Exercise actually helps lower BP long-term. Warm up and cool down properly.';
        }

        if ($ctx['has_heart']) {
            $lines[] = 'User has a heart condition. Exercise is beneficial but must be medically cleared first. Avoid very high intensity without clearance. Cardiac rehab programs are ideal. Monitor heart rate — stay in moderate zone (50-70% max HR). Stop immediately if chest pain, dizziness, or shortness of breath.';
        }

        if ($ctx['has_pcos']) {
            $lines[] = 'User has PCOS. Exercise is one of the most effective PCOS treatments — improves insulin resistance, hormone balance, and mood. Combination of strength training (3x/week) + moderate cardio (2-3x/week) is optimal. Avoid excessive cardio — it can increase cortisol and worsen hormonal imbalance. Consistency over intensity.';
        }

        if ($ctx['has_thyroid']) {
            $lines[] = 'User has thyroid condition. Hypothyroidism causes fatigue and slow metabolism — start slow and build gradually. Exercise helps but energy may be limited. Avoid overtraining. Hyperthyroidism — avoid very high intensity until levels are controlled. Both benefit from regular moderate exercise.';
        }

        if ($ctx['has_asthma']) {
            $lines[] = 'User has asthma. Always carry inhaler during exercise. Warm up slowly (10 min). Prefer swimming (humid air is easier on airways) and walking over running in cold/dry air. Avoid exercising in high pollution or cold weather. Breathing exercises (pranayama) can improve lung capacity over time.';
        }

        // ── Topic-specific guidance ───────────────────────────────
        if ($ctx['asking_about_cardio']) {
            $cardioNote = 'Cardio guidance: ';
            if ($ctx['goal_weight_loss']) {
                $cardioNote .= 'For fat loss — HIIT (20 min, 3x/week) is more time-efficient than steady-state cardio. Steady-state (30-45 min moderate pace) is better for beginners and recovery days. ';
            }
            $cardioNote .= 'Zone 2 cardio (can hold a conversation) builds aerobic base and burns fat efficiently. Minimum 150 min moderate cardio/week for health benefits.';
            $lines[] = $cardioNote;
        }

        if ($ctx['asking_about_strength']) {
            $strengthNote = 'Strength training guidance: ';
            if ($ctx['is_beginner']) {
                $strengthNote .= 'Beginners: full body 3x/week. Focus on compound movements — squat, hinge, push, pull, carry. Master bodyweight first. ';
            } elseif ($ctx['is_intermediate']) {
                $strengthNote .= 'Intermediate: upper/lower split or push/pull/legs 4x/week. Progressive overload — add 2.5-5kg when you can complete all reps with good form. ';
            } else {
                $strengthNote .= 'Advanced: periodised programming, deload every 4-6 weeks, track all lifts. ';
            }
            $strengthNote .= 'Rest 60-90 sec between sets for hypertrophy, 2-3 min for strength.';
            $lines[] = $strengthNote;
        }

        if ($ctx['asking_about_yoga']) {
            $lines[] = 'Yoga guidance: Surya Namaskar (12 rounds) is a complete full-body workout — burns ~150 kcal, improves flexibility, strength, and breathing. For beginners: Hatha yoga. For stress: Yin yoga. For strength: Power yoga/Ashtanga. For flexibility: Vinyasa. Even 20-30 min daily yoga has significant health benefits.';
        }

        if ($ctx['asking_about_walk']) {
            $lines[] = 'Walking guidance: 10,000 steps/day is a good target but even 7,000 steps has significant health benefits. Brisk walking (5-6 km/h) burns 250-300 kcal/hour. Post-meal 10-min walks are highly effective for blood sugar control and digestion. Increase by 500 steps/week if starting from low baseline.';
        }

        if ($ctx['asking_about_abs']) {
            $lines[] = 'Abs/core guidance: abs are made in the kitchen — visible abs require low body fat (diet). Core strength is different from visible abs. Best core exercises: plank (builds stability), dead bug (safe for back), bird-dog, hollow body hold, pallof press. Crunches are the least effective and hardest on the neck/spine. Train core 3x/week.';
        }

        if ($ctx['asking_about_plan']) {
            $planNote = 'When creating a workout plan: ';
            if ($ctx['is_beginner']) {
                $planNote .= 'Beginner plan: 3 days/week full body. Day 1: push (push-ups, squats, plank). Day 2: rest. Day 3: pull (rows, deadlift variation, lunges). Day 4: rest. Day 5: full body circuit. ';
            } elseif ($ctx['is_intermediate']) {
                $planNote .= 'Intermediate plan: 4 days/week upper/lower split. Upper A (push focus), Lower A (quad focus), Upper B (pull focus), Lower B (hip hinge focus). ';
            } else {
                $planNote .= 'Advanced plan: 5-6 days push/pull/legs or sport-specific periodisation. ';
            }
            if ($ctx['lifestyle']) {
                $planNote .= "User lifestyle: {$ctx['lifestyle']} — fit plan around their schedule. ";
            }
            $planNote .= 'Always include warm-up (5-10 min) and cool-down (5 min stretching).';
            $lines[] = $planNote;
        }

        if ($ctx['asking_about_frequency']) {
            $lines[] = 'Workout frequency: Beginners — 3 days/week (rest day between sessions). Intermediate — 4 days/week. Advanced — 5-6 days/week. Each muscle group needs 48-72 hours recovery. More is not always better — recovery is when muscles grow. Signs of overtraining: persistent fatigue, declining performance, poor sleep, irritability.';
        }

        if ($ctx['asking_about_duration']) {
            $lines[] = 'Workout duration: 30-45 min is optimal for most people. Beyond 60 min, cortisol rises and muscle breakdown increases. Quality over quantity — a focused 30-min session beats a distracted 90-min session. Warm-up: 5-10 min. Main workout: 20-35 min. Cool-down: 5 min.';
        }

        if ($ctx['asking_about_warm_up']) {
            $lines[] = 'Warm-up (never skip): 5-10 min of light cardio + dynamic stretches (leg swings, arm circles, hip rotations, bodyweight squats). Prepares joints, increases blood flow, reduces injury risk. Cool-down: 5 min of static stretching (hold 30 sec each). Reduces soreness and improves flexibility over time.';
        }

        if ($ctx['asking_about_recovery']) {
            $lines[] = 'Recovery guidance: muscles grow during rest, not during workout. DOMS (delayed onset muscle soreness) peaks 24-48 hours after workout — normal for beginners. Active recovery: light walk, yoga, swimming on rest days. Sleep 7-9 hours — growth hormone is released during deep sleep. Protein within 30-45 min post-workout speeds recovery.';
        }

        if ($ctx['asking_about_nutrition']) {
            $nutritionNote = 'Workout nutrition: ';
            $nutritionNote .= 'Pre-workout (1-2 hours before): complex carbs + small protein (banana + curd, oats, roti + dal). ';
            $nutritionNote .= 'Post-workout (within 30-45 min): protein + fast carbs (whey + banana, eggs + rice, paneer + roti). ';
            if ($ctx['is_veg']) {
                $nutritionNote .= 'Vegetarian protein options: whey protein, paneer, curd, soya chunks, dal, rajma. ';
            } else {
                $nutritionNote .= 'Protein options: chicken breast, eggs, fish, whey protein, paneer, dal. ';
            }
            $nutritionNote .= 'Creatine monohydrate (3-5g/day) is the most evidence-backed supplement for strength and muscle — safe for most people.';
            $lines[] = $nutritionNote;
        }

        if ($ctx['asking_about_plateau']) {
            $lines[] = 'Fitness plateau: body adapts to the same stimulus. Solutions: change exercise variation, increase weight/reps/sets (progressive overload), change rep range (e.g. from 3x12 to 5x5), add a new training technique (supersets, drop sets, tempo training), take a deload week (reduce volume by 40-50% for 1 week), check diet — plateau is often a calorie/protein issue.';
        }

        if ($ctx['asking_about_motivation']) {
            $lines[] = 'Motivation and consistency: motivation is unreliable — build systems instead. Schedule workouts like meetings. Start with minimum viable workout (even 10 min). Track progress (photos, measurements, strength numbers) — visible progress is the best motivator. Find an accountability partner or class. Remember: showing up consistently at 70% effort beats perfect workouts 2x/month.';
        }

        // ── BMI context ───────────────────────────────────────────
        if ($ctx['bmi']) {
            $bmiNote = "User BMI: {$ctx['bmi']}. ";
            $bmiNote .= match(true) {
                $ctx['bmi'] < 18.5 => 'Underweight — focus on strength training and calorie surplus. Avoid excessive cardio.',
                $ctx['bmi'] < 25   => 'Healthy weight — focus on body composition (muscle gain + fat loss) rather than scale weight.',
                $ctx['bmi'] < 30   => 'Overweight — combination of strength training and moderate cardio. Diet is 80% of the result.',
                default            => 'Obese — start with low-impact exercise (walking, swimming, cycling). Joint protection is priority. Even small amounts of movement have significant health benefits.',
            };
            $lines[] = $bmiNote;
        }

        // ── Age and gender specific ───────────────────────────────
        if ($ctx['gender'] === 'female') {
            $lines[] = 'Female user: strength training is especially important for women — builds bone density, boosts metabolism, and does NOT make you bulky (women have 10-20x less testosterone than men). Hormonal fluctuations affect energy and performance — lower intensity during menstruation is normal and okay.';
        }

        if ($ctx['age'] && $ctx['age'] >= 40) {
            $lines[] = 'User 40+: muscle loss (sarcopenia) accelerates after 40 — strength training 2-3x/week is essential to counter this. Joint health becomes more important — warm up thoroughly, avoid ego lifting. Recovery takes longer — add an extra rest day. Flexibility and mobility work becomes increasingly important.';
        }

        if ($ctx['age'] && $ctx['age'] >= 60) {
            $lines[] = 'User 60+: focus on functional fitness — balance, coordination, flexibility, and light strength training. Prevent falls (single-leg exercises, balance work). Chair exercises and resistance bands are excellent. Walking is highly beneficial. Avoid high-impact activities unless already conditioned.';
        }

        // ── Lifestyle and challenge context ──────────────────────
        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Design workout plan around their actual schedule — not an ideal one.";
        }

        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these directly — e.g. if no time, suggest 20-min home workouts.";
        }

        if ($ctx['sleep']) {
            $lines[] = "Sleep pattern: {$ctx['sleep']}. Poor sleep significantly impairs recovery, muscle growth, and fat loss. If sleep is poor, address it alongside fitness.";
        }

        // ── Build final block ─────────────────────────────────────
        $block = "FITNESS COACHING CONTEXT (use this intelligence to give specific, personalised fitness advice — never generic):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function calculateBMI(float $weight, float $height): float
    {
        $heightM = $height / 100;
        return round($weight / ($heightM * $heightM), 1);
    }
}
