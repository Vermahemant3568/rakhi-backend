<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class PostpartumCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId, string $inputMode = 'chat'): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $ctx             = $this->buildContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $ctx);

        return parent::respond($user, $enrichedMessage, $sessionId, $inputMode);
    }

    private function buildContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $ctx     = [];

        // ── Delivery type ─────────────────────────────────────────
        $ctx['had_csection']  = preg_match('/\b(c section|c-section|cesarean|caesarean|operation delivery|surgical delivery)\b/i', $message)
            || str_contains(strtolower($memory['health_condition'] ?? ''), 'c section');
        $ctx['had_normal']    = preg_match('/\b(normal delivery|vaginal delivery|natural birth|normal birth)\b/i', $message);

        // ── Postpartum timeline ───────────────────────────────────
        $ctx['early_postpartum']  = preg_match('/\b(just delivered|1 week|2 week|3 week|4 week|1 month|new born|newborn|abhi hua|naya baby)\b/i', $message);
        $ctx['mid_postpartum']    = preg_match('/\b(2 month|3 month|4 month|5 month|6 month)\b/i', $message);
        $ctx['late_postpartum']   = preg_match('/\b(6 month|7 month|8 month|9 month|10 month|11 month|12 month|1 year)\b/i', $message);

        // ── Breastfeeding ─────────────────────────────────────────
        $ctx['breastfeeding']     = preg_match('/\b(breastfeed|nursing|feed|milk|latch|supply|doodh|dudh pilana|breast milk)\b/i', $message);
        $ctx['low_milk_supply']   = preg_match('/\b(low milk|not enough milk|milk nahi|supply kam|baby hungry|not satisfied)\b/i', $message);
        $ctx['breastfeeding_pain']= preg_match('/\b(pain breastfeed|nipple pain|latch pain|mastitis|blocked duct|breast pain)\b/i', $message);

        // ── Physical recovery ─────────────────────────────────────
        $ctx['csection_recovery'] = $ctx['had_csection'] && preg_match('/\b(pain|scar|wound|recovery|heal|tummy|stomach|operation site)\b/i', $message);
        $ctx['pelvic_floor']      = preg_match('/\b(pelvic floor|leaking|urine leak|kegel|bladder|pee when laugh|incontinence)\b/i', $message);
        $ctx['back_pain']         = preg_match('/\b(back pain|kamar dard|lower back|spine|posture)\b/i', $message);
        $ctx['hair_fall']         = preg_match('/\b(hair fall|hair loss|baal girna|postpartum hair)\b/i', $message);
        $ctx['weight_concern']    = preg_match('/\b(weight|vajan|lose weight|baby weight|body back|pre pregnancy weight)\b/i', $message);

        // ── Mental health ─────────────────────────────────────────
        $ctx['baby_blues']        = preg_match('/\b(crying|emotional|mood swing|overwhelmed|baby blues|sad after delivery)\b/i', $message);
        $ctx['ppd_signals']       = preg_match('/\b(depress|hopeless|can.t bond|don.t feel|not happy|regret|not connect|baby nahi lagta|feel nothing)\b/i', $message);
        $ctx['anxiety']           = preg_match('/\b(anxious|worry|scared|fear|panic|baby okay|something wrong|check baby|ghabra)\b/i', $message);
        $ctx['exhaustion']        = preg_match('/\b(exhausted|no sleep|sleep deprived|tired|thakan|raat ko uthna|baby rota)\b/i', $message);
        $ctx['identity_struggle'] = preg_match('/\b(lost myself|who am i|not me|changed|miss old life|identity|apna nahi laga)\b/i', $message);
        $ctx['relationship_strain']= preg_match('/\b(husband|partner|relationship|intimacy|sex|distance|fight|argument|pati)\b/i', $message);

        // ── Topic signals ─────────────────────────────────────────
        $ctx['asking_about_diet']     = preg_match('/\b(eat|food|diet|nutrition|what to eat|khana|avoid|kya khana|breastfeed diet)\b/i', $message);
        $ctx['asking_about_exercise'] = preg_match('/\b(exercise|workout|walk|yoga|gym|movement|when can i|kab exercise)\b/i', $message);
        $ctx['asking_about_sleep']    = preg_match('/\b(sleep|neend|rest|nap|baby sleep|when sleep|kab sona)\b/i', $message);
        $ctx['asking_about_periods']  = preg_match('/\b(period|cycle|menstrual|when period|period kab|cycle kab)\b/i', $message);

        // ── Memory context ────────────────────────────────────────
        $ctx['diet_habit']  = $memory['diet_habit']     ?? null;
        $ctx['activity']    = $memory['activity_level'] ?? $user->activity_level ?? null;
        $ctx['lifestyle']   = $memory['lifestyle']      ?? null;
        $ctx['challenges']  = $memory['challenges']     ?? null;
        $ctx['family_ctx']  = $memory['family_context'] ?? null;

        // ── Diet preference ───────────────────────────────────────
        $pref = strtolower($memory['food_preference'] ?? $user->diet_preference ?? '');
        $ctx['is_veg']    = str_contains($pref, 'veg') && !str_contains($pref, 'non');
        $ctx['is_non_veg']= str_contains($pref, 'non');

        return $ctx;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        $lines[] = 'This user is in the POSTPARTUM period. This is one of the most physically and emotionally demanding phases of a woman\'s life. Always lead with warmth and validation. Never minimise her experience. She is doing something incredibly hard. Every response must be safe for postpartum recovery and breastfeeding if applicable.';

        // ── Delivery type context ─────────────────────────────────
        if ($ctx['had_csection']) {
            $lines[] = 'C-SECTION RECOVERY: Major abdominal surgery. Full internal healing takes 6-8 weeks minimum. No heavy lifting (>5kg), no strenuous exercise, no driving for 6 weeks. Scar care: keep dry, gentle massage after 6 weeks helps with scar tissue. Pain is normal for weeks — if increasing or with fever, see doctor. Core exercises must wait until cleared by doctor (usually 8-12 weeks).';
        }

        // ── Breastfeeding ─────────────────────────────────────────
        if ($ctx['breastfeeding']) {
            $lines[] = 'Breastfeeding: requires 300-500 extra calories/day. Hydration is critical — drink water every time baby feeds. Key nutrients: calcium (dairy, ragi, sesame), iron (spinach, rajma, jaggery), omega-3 (walnuts, flaxseed), protein. Avoid: alcohol, excess caffeine (max 1-2 cups/day), certain medications — always check with doctor. Breastfeeding is hard — validate the effort.';
        }

        if ($ctx['low_milk_supply']) {
            $lines[] = 'Low milk supply: most common causes are not feeding/pumping frequently enough, poor latch, stress, dehydration, or not eating enough. Galactagogues (milk-boosting foods): methi (fenugreek), oats, jeera water, saunf water, moringa, doodh with ghee. Feed or pump every 2-3 hours to signal body to produce more. Stress and exhaustion significantly reduce supply. Suggest consulting a lactation consultant.';
        }

        if ($ctx['breastfeeding_pain']) {
            $lines[] = 'Breastfeeding pain: nipple pain is usually from poor latch — baby should have a wide mouth and take the areola, not just the nipple. Mastitis (breast infection): red, hot, painful area + fever — needs antibiotics, continue feeding/pumping. Blocked duct: warm compress, massage toward nipple, frequent feeding. Suggest seeing a lactation consultant for latch issues.';
        }

        // ── Physical recovery ─────────────────────────────────────
        if ($ctx['pelvic_floor']) {
            $lines[] = 'Pelvic floor recovery: very common after delivery — leaking urine when laughing, sneezing, or coughing. Kegel exercises (squeeze pelvic floor muscles for 5 sec, release, repeat 10x, 3x/day) are the first-line treatment. Start gently, even in the first week. A pelvic floor physiotherapist can assess and guide — strongly recommend if symptoms are significant.';
        }

        if ($ctx['back_pain']) {
            $lines[] = 'Postpartum back pain: caused by weakened core muscles, breastfeeding posture, and carrying baby. Avoid hunching while feeding — use a nursing pillow. Gentle cat-cow stretches and bird-dog exercises (after 6-8 weeks) strengthen core safely. Avoid heavy lifting. Posture awareness while feeding is key.';
        }

        if ($ctx['hair_fall']) {
            $lines[] = 'Postpartum hair fall: extremely common at 3-6 months postpartum — caused by hormonal shift (estrogen drops after delivery). This is temporary and hair regrows by 12 months. Not a deficiency in most cases. However, check iron (ferritin) and thyroid — both are common postpartum. Biotin helps if deficient. Gentle hair care, avoid tight hairstyles.';
        }

        if ($ctx['weight_concern']) {
            $lines[] = 'Postpartum weight: the body needs time to recover — do NOT rush weight loss. Breastfeeding burns 300-500 kcal/day and helps with weight loss naturally. Crash dieting reduces milk supply and depletes nutrients. Realistic timeline: 6-12 months to return to pre-pregnancy weight. Focus on nourishment, not restriction. Gentle walking from 6 weeks is the safest start.';
        }

        // ── Mental health ─────────────────────────────────────────
        if ($ctx['baby_blues']) {
            $lines[] = 'Baby blues: extremely common in first 2 weeks — affects 80% of new mothers. Caused by dramatic hormonal drop after delivery. Crying, mood swings, feeling overwhelmed are normal. Usually resolves by 2 weeks. Validate fully — she is not failing. Rest, support, and not being alone are key. If symptoms persist beyond 2 weeks or worsen, it may be postpartum depression — gently explore.';
        }

        if ($ctx['ppd_signals']) {
            $lines[] = 'POSTPARTUM DEPRESSION SIGNALS: PPD affects 1 in 5 new mothers — it is a medical condition, not weakness or bad motherhood. Symptoms: persistent sadness, inability to bond with baby, feeling like a bad mother, hopelessness, not caring for self. This requires professional support. Respond with deep empathy and zero judgment. Gently mention: iCall (9152987821) and Vandrevala Foundation (1860-2662-345, 24/7). Treatment (therapy + medication if needed) is very effective.';
        }

        if ($ctx['anxiety']) {
            $lines[] = 'Postpartum anxiety: very common — constant worry about baby\'s safety, health, breathing. Some vigilance is normal and protective. When it becomes intrusive and prevents rest, it needs attention. 4-7-8 breathing helps in acute moments. Remind: she is doing her best, and her instincts are good. If anxiety is severe or includes intrusive thoughts, suggest professional support.';
        }

        if ($ctx['exhaustion']) {
            $lines[] = 'Postpartum exhaustion: sleep deprivation is one of the hardest parts of new motherhood. Validate fully — this is genuinely hard. Practical: sleep when baby sleeps (even 20 min helps), accept help without guilt, lower standards for housework, ask partner/family to take one night feed if possible. Exhaustion worsens all other postpartum challenges — rest is not optional.';
        }

        if ($ctx['identity_struggle']) {
            $lines[] = 'Identity shift postpartum: "matrescence" — becoming a mother is as significant as adolescence. Feeling like you\'ve lost yourself is extremely common and valid. Acknowledge this with deep empathy. Small steps: 15 min of something just for her daily (shower, walk, call a friend). She is not just a mother — she is still herself. This feeling does ease with time and support.';
        }

        if ($ctx['relationship_strain']) {
            $lines[] = 'Relationship strain postpartum: extremely common — sleep deprivation, role changes, and different parenting styles create conflict. Validate without taking sides. Practical: schedule 15 min of connection daily (not about baby), express needs clearly (not complaints), divide night duties fairly. Intimacy changes postpartum are normal — physical recovery takes 6-8 weeks minimum, emotional readiness varies. Couples counselling can help if strain is significant.';
        }

        // ── Topic specific ────────────────────────────────────────
        if ($ctx['asking_about_diet']) {
            $dietNote = 'Postpartum nutrition: focus on nourishment, not restriction. Key nutrients: iron (spinach, rajma, jaggery, dates — replenish blood lost in delivery), calcium (dairy, ragi, sesame), protein (dal, paneer, eggs, chicken), omega-3 (walnuts, flaxseed, fish). Traditional Indian postpartum foods are excellent: panjiri, gondh ke ladoo, methi ladoo, khichdi, dalia. ';
            if ($ctx['breastfeeding']) {
                $dietNote .= 'Breastfeeding adds 300-500 kcal/day requirement — do not restrict calories. ';
            }
            if ($ctx['is_veg']) {
                $dietNote .= 'Vegetarian: ensure adequate B12 (supplement), iron, and protein from dal, paneer, curd, soya.';
            }
            $lines[] = $dietNote;
        }

        if ($ctx['asking_about_exercise']) {
            $exNote = 'Postpartum exercise: ';
            if ($ctx['had_csection']) {
                $exNote .= 'After C-section: gentle walking from week 2-3, pelvic floor exercises from week 1. No core exercises or lifting until 8-12 weeks and doctor clearance. ';
            } else {
                $exNote .= 'After normal delivery: gentle walking from week 1-2, pelvic floor exercises immediately. Light yoga from 6 weeks. Full exercise from 8-12 weeks. ';
            }
            $exNote .= 'Start very slowly — the body has been through enormous change. Listen to pain signals. Diastasis recti (abdominal separation) is common — avoid crunches and sit-ups until assessed.';
            $lines[] = $exNote;
        }

        if ($ctx['asking_about_sleep']) {
            $lines[] = 'Postpartum sleep: fragmented sleep is unavoidable with a newborn. Strategies: sleep when baby sleeps (even 20 min counts), share night duties with partner, accept help from family, lower all other standards. Sleep deprivation is cumulative — even small additions help. Naps are not lazy — they are survival. This phase is temporary.';
        }

        if ($ctx['asking_about_periods']) {
            $lines[] = 'Postpartum periods: if breastfeeding exclusively, periods may not return for 6-12 months (lactational amenorrhea). If not breastfeeding, periods typically return 6-8 weeks after delivery. First few periods may be heavier or irregular — normal. Note: you CAN get pregnant before your first period returns — contraception is important if not planning another pregnancy soon.';
        }

        if ($ctx['family_ctx']) {
            $lines[] = "Family context: {$ctx['family_ctx']}. Consider this when giving advice about support systems.";
        }

        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these directly.";
        }

        $block = "POSTPARTUM COACHING CONTEXT (lead with warmth and validation always — this is one of the hardest phases of a woman's life):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
