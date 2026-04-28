<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class StressCoach extends BaseCoach
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

        // ── Stress type ───────────────────────────────────────────
        $ctx['work_stress']       = preg_match('/\b(work|job|office|boss|deadline|workload|career|colleague|naukri|kaam)\b/i', $message);
        $ctx['family_stress']     = preg_match('/\b(family|parents|spouse|husband|wife|kids|children|in-laws|ghar|gharwale|shaadi)\b/i', $message);
        $ctx['financial_stress']  = preg_match('/\b(money|financial|debt|loan|EMI|salary|afford|paisa|karz)\b/i', $message);
        $ctx['health_stress']     = preg_match('/\b(health anxiety|illness|disease|diagnosis|scared|bimari|darr)\b/i', $message);
        $ctx['exam_stress']       = preg_match('/\b(exam|study|result|marks|fail|board|UPSC|JEE|NEET|pariksha)\b/i', $message);
        $ctx['relationship_stress']= preg_match('/\b(relationship|breakup|divorce|fight|argument|partner|rishta)\b/i', $message);

        // ── Severity signals ──────────────────────────────────────
        $ctx['high_severity']     = preg_match('/\b(can.t cope|breaking point|falling apart|can.t function|toot gaya|hadd ho gayi|bahut bura)\b/i', $message);
        $ctx['burnout']           = preg_match('/\b(burnout|burnt out|exhausted|done|finished|no energy|toot gaya|thak gaya completely)\b/i', $message);
        $ctx['panic_attack']      = preg_match('/\b(panic attack|heart racing|chest tight|can.t breathe|dizzy|panic|ghabra|dil tez)\b/i', $message);
        $ctx['physical_symptoms'] = preg_match('/\b(headache|chest pain|stomach ache|nausea|muscle tension|jaw tight|sar dard|pet dard|body tight)\b/i', $message);

        // ── Coping signals ────────────────────────────────────────
        $ctx['poor_sleep']        = preg_match('/\b(can.t sleep|insomnia|sleep deprived|neend nahi|raat ko uthna)\b/i', $message);
        $ctx['appetite_change']   = preg_match('/\b(not eating|overeating|stress eating|no appetite|binge|khana nahi|bahut kha)\b/i', $message);
        $ctx['social_withdrawal'] = preg_match('/\b(avoiding|isolating|don.t want to meet|staying home|log se milna nahi)\b/i', $message);
        $ctx['irritability']      = preg_match('/\b(irritable|snapping|angry|short temper|gussa|chidchida|react badly)\b/i', $message);

        // ── Topic signals ─────────────────────────────────────────
        $ctx['asking_for_techniques'] = preg_match('/\b(how to|technique|method|kaise|what can i do|help me calm|calm down|relax kaise)\b/i', $message);
        $ctx['asking_about_breathing']= preg_match('/\b(breathing|breath|pranayama|anulom|4-7-8|box breathing|saans)\b/i', $message);
        $ctx['asking_about_meditation']= preg_match('/\b(meditat|mindfulness|dhyan|calm|guided|app)\b/i', $message);
        $ctx['asking_about_exercise'] = preg_match('/\b(exercise|walk|yoga|gym|movement|physical|workout)\b/i', $message);
        $ctx['asking_about_boundaries']= preg_match('/\b(boundary|boundaries|say no|limit|overcommit|too much yes|na bolna)\b/i', $message);
        $ctx['asking_about_therapy']  = preg_match('/\b(therapy|therapist|counsellor|professional help|kya therapy)\b/i', $message);

        // ── Health conditions ─────────────────────────────────────
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $ctx['has_bp']       = str_contains($healthCond, 'blood pressure') || str_contains($lower, 'bp') || str_contains($lower, 'hypertension');
        $ctx['has_diabetes'] = str_contains($healthCond, 'diabet') || str_contains($lower, 'diabet');
        $ctx['has_pcos']     = str_contains($healthCond, 'pcos') || str_contains($lower, 'pcos');
        $ctx['has_thyroid']  = str_contains($healthCond, 'thyroid') || str_contains($lower, 'thyroid');
        $ctx['has_heart']    = str_contains($healthCond, 'heart') || str_contains($lower, 'heart condition');

        // ── Memory context ────────────────────────────────────────
        $ctx['stress_level'] = $memory['stress_level']   ?? $user->stress_level ?? null;
        $ctx['sleep_pattern']= $memory['sleep_pattern']  ?? null;
        $ctx['activity']     = $memory['activity_level'] ?? $user->activity_level ?? null;
        $ctx['lifestyle']    = $memory['lifestyle']      ?? null;
        $ctx['challenges']   = $memory['challenges']     ?? null;
        $ctx['family_ctx']   = $memory['family_context'] ?? null;
        $ctx['age']          = $user->getAge() > 0 ? $user->getAge() : null;
        $ctx['gender']       = $user->gender ?? null;

        return $ctx;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        $lines[] = 'You are a stress management coach. ALWAYS acknowledge the stress first before offering any techniques. Never minimise what the user is feeling. Stress is real and has physical consequences. Your role is to validate, then help them find practical tools that fit their actual life.';

        if ($ctx['stress_level']) {
            $lines[] = "Known stress level: {$ctx['stress_level']}. Use this context.";
        }

        // ── High severity ─────────────────────────────────────────
        if ($ctx['high_severity']) {
            $lines[] = 'HIGH SEVERITY: User is at or near breaking point. Respond with deep empathy first. Do NOT immediately give a list of techniques — that feels dismissive when someone is overwhelmed. Ask one gentle question to understand what is happening. Mention that iCall (9152987821) and Vandrevala Foundation (1860-2662-345, 24/7) are free helplines if they need to talk to someone.';
        }

        if ($ctx['burnout']) {
            $lines[] = 'BURNOUT: This is not just tiredness — it is emotional exhaustion from prolonged stress. Recovery requires genuine rest (not just sleep), boundary-setting, and reconnecting with meaning. The first step is acknowledging it is real. Ask: what has been draining them the most? Recovery takes weeks to months — set realistic expectations. Returning to the same conditions without change will cause relapse.';
        }

        if ($ctx['panic_attack']) {
            $lines[] = 'PANIC ATTACK: If currently happening — guide them through 4-7-8 breathing immediately (inhale 4 counts, hold 7, exhale 8). Or box breathing (4-4-4-4). Or cold water on face/wrists (activates dive reflex, slows heart rate within 30 seconds). Reassure: panic attacks are not dangerous — they feel terrifying but cannot harm them. They peak at 10 min and pass. After it passes, explore triggers.';
        }

        if ($ctx['physical_symptoms']) {
            $lines[] = 'Physical stress symptoms (headache, chest tightness, stomach ache, muscle tension): these are real physical manifestations of stress — not imagined. Cortisol causes inflammation, muscle tension, and digestive disruption. Acknowledge the physical pain first. Progressive muscle relaxation (tense and release each muscle group) directly addresses muscle tension. If chest pain is severe, advise seeing a doctor to rule out cardiac causes.';
        }

        // ── Stress source specific ────────────────────────────────
        if ($ctx['work_stress']) {
            $lines[] = 'Work stress: identify the specific stressor — is it workload, relationships, lack of control, or lack of recognition? Different causes need different solutions. Workload: prioritisation (not everything is urgent), time-blocking, saying no to non-essential tasks. Toxic environment: document issues, set boundaries, consider whether the job is worth the health cost. End-of-day shutdown ritual: write 3 things done, close laptop, change clothes — signals brain that work is over.';
        }

        if ($ctx['family_stress']) {
            $familyNote = 'Family stress: ';
            if ($ctx['family_ctx']) {
                $familyNote .= "Known family context: {$ctx['family_ctx']}. ";
            }
            $familyNote .= 'Indian family dynamics add unique pressure — joint families, expectations, lack of privacy. Validate that family stress is real and often complex. Focus on: what they can control (their response, their boundaries), communication (I-statements: "I feel X when Y happens"), and carving out personal time even in a joint family setup. They cannot pour from an empty cup.';
            $lines[] = $familyNote;
        }

        if ($ctx['financial_stress']) {
            $lines[] = 'Financial stress: money stress is one of the most physically damaging forms of stress — it is constant and inescapable. Do NOT give financial advice (outside scope). Focus on the emotional side: the anxiety, the shame, the fear of the future. Practical mental health tools: separate what is in their control from what is not, avoid catastrophising ("I will always be broke"), one small action per day reduces helplessness.';
        }

        if ($ctx['exam_stress']) {
            $lines[] = 'Exam/academic stress: validate the pressure — it is real and intense in India. Practical: break study into small chunks (Pomodoro — 25 min work, 5 min break), adequate sleep is non-negotiable for memory consolidation, exercise improves focus and reduces cortisol, avoid all-nighters (they impair performance). Perspective: one exam does not define a life — but do not dismiss the pressure they feel.';
        }

        // ── Coping pattern concerns ───────────────────────────────
        if ($ctx['poor_sleep']) {
            $lines[] = 'Stress and sleep: they are bidirectional — stress disrupts sleep, poor sleep worsens stress response. Priority: fix sleep alongside stress. 4-7-8 breathing before bed. Write down tomorrow\'s tasks before sleeping (offloads the brain). No screens 30 min before bed.';
        }

        if ($ctx['appetite_change']) {
            $lines[] = 'Stress eating or loss of appetite: both are normal stress responses. Cortisol increases cravings for high-calorie foods. Validate — do not shame. Practical: keep healthy snacks available, eat at regular times even if not hungry (blood sugar stability reduces stress reactivity), identify emotional triggers before reaching for food.';
        }

        if ($ctx['irritability']) {
            $lines[] = 'Irritability from stress: when the nervous system is in chronic fight-or-flight, the threshold for frustration drops significantly. This is physiological, not a character flaw. Acknowledge this with compassion. Physical exercise is the fastest way to metabolise cortisol and adrenaline. 10-min walk when feeling reactive can prevent regrettable reactions.';
        }

        // ── Technique requests ────────────────────────────────────
        if ($ctx['asking_for_techniques'] || $ctx['asking_about_breathing']) {
            $lines[] = 'Breathing techniques (most immediate stress relief): 1) 4-7-8 breathing — inhale 4, hold 7, exhale 8. Activates vagus nerve within 90 seconds. 2) Box breathing — 4 counts each side. Used by Navy SEALs for acute stress. 3) Physiological sigh — double inhale through nose, long exhale through mouth. Fastest way to reduce physiological arousal. 4) Anulom Vilom (alternate nostril) — balances nervous system, excellent for chronic stress.';
        }

        if ($ctx['asking_about_meditation']) {
            $lines[] = 'Meditation for stress: start with 5 minutes, not 20. Guided apps (Headspace, Calm, Insight Timer — free) are easier for beginners. Body scan meditation is excellent for physical stress symptoms. Loving-kindness meditation reduces anger and social stress. Even 5 min of focused breathing daily reduces cortisol measurably within 2 weeks. Consistency matters more than duration.';
        }

        if ($ctx['asking_about_exercise']) {
            $lines[] = 'Exercise for stress: the most effective stress reliever — metabolises cortisol and adrenaline directly. Even a 10-min brisk walk reduces cortisol by 15-20%. Yoga combines movement with breathing — especially effective for chronic stress. Strength training provides a healthy outlet for frustration. The key is doing it consistently, not intensely.';
        }

        if ($ctx['asking_about_boundaries']) {
            $lines[] = 'Boundaries and stress: many people are stressed because they cannot say no. Boundaries are not selfish — they are necessary for sustainable functioning. Start small: "I cannot take that on right now." You do not need to explain or justify. Identify one area where you are consistently overcommitting and practice one boundary this week. Boundaries reduce resentment and prevent burnout.';
        }

        if ($ctx['asking_about_therapy']) {
            $lines[] = 'Therapy for stress: therapy is not only for severe cases — it is for anyone who wants better tools to cope. CBT (Cognitive Behavioural Therapy) is the most evidence-based for stress and anxiety. In India: iCall (free, trained counsellors — 9152987821), YourDOST, Vandrevala Foundation (24/7 free). Online therapy is accessible and affordable. Normalise it — seeing a therapist is like going to the gym for your mind.';
        }

        // ── Health condition specific ─────────────────────────────
        if ($ctx['has_bp']) {
            $lines[] = 'High blood pressure and stress: stress directly raises blood pressure through cortisol and adrenaline. Chronic stress is a significant cardiovascular risk factor. Stress management is medical management for BP. Breathing exercises (especially slow exhale) lower BP within minutes. Regular exercise, adequate sleep, and reducing sodium all help.';
        }

        if ($ctx['has_diabetes']) {
            $lines[] = 'Diabetes and stress: cortisol raises blood sugar directly — stress management is blood sugar management. Chronic stress makes diabetes harder to control. Breathing exercises and yoga have been shown to reduce HbA1c. Identify and address the main stress source as a diabetes management strategy.';
        }

        if ($ctx['has_pcos']) {
            $lines[] = 'PCOS and stress: cortisol worsens insulin resistance and androgen production — stress management is PCOS management. Yoga is especially beneficial for PCOS — reduces cortisol and improves hormonal balance. Avoid excessive exercise (also raises cortisol). Prioritise sleep and rest.';
        }

        if ($ctx['has_heart']) {
            $lines[] = 'Heart condition and stress: chronic stress is a major cardiac risk factor. Stress management is cardiac management. Breathing exercises, meditation, and gentle yoga are safe and beneficial. Avoid high-intensity exercise without medical clearance. If experiencing chest pain or palpitations during stress, advise seeing a doctor.';
        }

        // ── Gender specific ───────────────────────────────────────
        if ($ctx['gender'] === 'female') {
            $lines[] = 'Female stress response: women tend to "tend and befriend" under stress (seek social connection) rather than fight-or-flight. Social support is especially important for women\'s stress management. Hormonal fluctuations (menstrual cycle, PCOS, perimenopause) amplify stress reactivity. Acknowledge this biological reality.';
        }

        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Suggest stress management tools that fit their actual schedule — not an ideal one.";
        }

        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these directly.";
        }

        $block = "STRESS COACHING CONTEXT (validate feelings first, then offer specific practical tools — never give a generic list of 10 stress tips):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
