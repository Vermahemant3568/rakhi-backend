<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class SleepCoach extends BaseCoach
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

        // ── Sleep problem type ────────────────────────────────────
        $ctx['cant_fall_asleep']   = preg_match('/\b(can.t sleep|cant sleep|fall asleep|neend nahi aati|so nahi pata|lying awake|hours to sleep)\b/i', $message);
        $ctx['wakes_up_at_night']  = preg_match('/\b(wake up|waking up|raat ko uthna|middle of night|3am|2am|4am|disturbed sleep|broken sleep)\b/i', $message);
        $ctx['early_waking']       = preg_match('/\b(early morning|wake up early|4am|5am|can.t go back|subah jaldi uthna)\b/i', $message);
        $ctx['oversleeping']       = preg_match('/\b(oversleep|sleep too much|can.t get up|zyada sota|zyada neend|10 hours|12 hours)\b/i', $message);
        $ctx['poor_quality']       = preg_match('/\b(not refreshed|tired after sleep|poor quality|light sleep|not deep|neend puri nahi|uthke thaka)\b/i', $message);
        $ctx['racing_mind']        = preg_match('/\b(racing mind|overthink|thoughts|can.t stop thinking|anxiety at night|worry at night|dimag band nahi)\b/i', $message);
        $ctx['snoring']            = preg_match('/\b(snore|snoring|sleep apnea|apnoea|stop breathing|kharrate|gasping)\b/i', $message);
        $ctx['restless_legs']      = preg_match('/\b(restless leg|leg cramp|leg movement|tingling legs|paon mein|legs uncomfortable)\b/i', $message);
        $ctx['nightmares']         = preg_match('/\b(nightmare|bad dream|bura sapna|scary dream|night terror)\b/i', $message);

        // ── Root cause signals ────────────────────────────────────
        $ctx['stress_related']     = preg_match('/\b(stress|anxiety|tension|worry|overthink|work pressure|pareshan)\b/i', $message);
        $ctx['screen_related']     = preg_match('/\b(phone|screen|mobile|laptop|tv|scroll|social media|reel|youtube)\b/i', $message);
        $ctx['caffeine_related']   = preg_match('/\b(coffee|chai|tea|caffeine|energy drink|green tea|late chai)\b/i', $message);
        $ctx['late_eating']        = preg_match('/\b(late dinner|eat late|raat ko khana|late night eating|heavy dinner)\b/i', $message);
        $ctx['irregular_schedule'] = preg_match('/\b(irregular|different time|no schedule|weekend|shift work|night shift|late night)\b/i', $message);
        $ctx['environment']        = preg_match('/\b(noise|light|hot|cold|uncomfortable|room|mattress|pillow|partner snore)\b/i', $message);

        // ── Health conditions affecting sleep ─────────────────────
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $ctx['has_anxiety']   = str_contains($healthCond, 'anxiety') || str_contains($lower, 'anxiety');
        $ctx['has_depression']= str_contains($healthCond, 'depress') || str_contains($lower, 'depress');
        $ctx['has_thyroid']   = str_contains($healthCond, 'thyroid') || str_contains($lower, 'thyroid');
        $ctx['has_pcos']      = str_contains($healthCond, 'pcos')    || str_contains($lower, 'pcos');
        $ctx['has_diabetes']  = str_contains($healthCond, 'diabet')  || str_contains($lower, 'diabet');
        $ctx['is_pregnant']   = str_contains($healthCond, 'pregnan') || str_contains($lower, 'pregnan');

        // ── Topic signals ─────────────────────────────────────────
        $ctx['asking_about_routine']     = preg_match('/\b(routine|schedule|bedtime|what time|kab sona|sleep time|night routine)\b/i', $message);
        $ctx['asking_about_supplements'] = preg_match('/\b(supplement|melatonin|magnesium|ashwagandha|tablet|medicine|natural)\b/i', $message);
        $ctx['asking_about_nap']         = preg_match('/\b(nap|afternoon sleep|daytime sleep|dopahar sona|power nap)\b/i', $message);
        $ctx['asking_about_hours']       = preg_match('/\b(how many hours|kitne ghante|enough sleep|how much sleep|8 hours|7 hours)\b/i', $message);

        // ── Memory context ────────────────────────────────────────
        $ctx['sleep_pattern'] = $memory['sleep_pattern']  ?? null;
        $ctx['stress_level']  = $memory['stress_level']   ?? $user->stress_level ?? null;
        $ctx['activity']      = $memory['activity_level'] ?? $user->activity_level ?? null;
        $ctx['lifestyle']     = $memory['lifestyle']      ?? null;
        $ctx['challenges']    = $memory['challenges']     ?? null;
        $ctx['age']           = $user->getAge() > 0 ? $user->getAge() : null;
        $ctx['gender']        = $user->gender ?? null;

        return $ctx;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        $lines[] = 'You are a sleep coach. Sleep is foundational to all health — it affects weight, hormones, immunity, mood, and cognition. Never dismiss sleep problems as "just stress." Identify the specific sleep issue and address its root cause.';

        if ($ctx['sleep_pattern']) {
            $lines[] = "Known sleep pattern: {$ctx['sleep_pattern']}. Use this context when responding.";
        }

        // ── Sleep problem specific guidance ──────────────────────
        if ($ctx['cant_fall_asleep']) {
            $lines[] = 'Cannot fall asleep: most common causes are racing mind, irregular sleep schedule, too much light/screen exposure, caffeine too late, or anxiety. The bed should be associated ONLY with sleep — not phone, work, or TV. If not asleep in 20 min, get up and do something calm until sleepy. Sleep restriction therapy is the most effective treatment for chronic insomnia.';
        }

        if ($ctx['wakes_up_at_night']) {
            $lines[] = 'Waking up at night: causes include sleep apnea (especially if snoring), blood sugar drops (diabetics), anxiety, needing to urinate (reduce fluids after 7pm), light/noise, or poor sleep architecture. Waking once briefly is normal — waking multiple times and struggling to return to sleep is not. Ask about snoring and breathing.';
        }

        if ($ctx['early_waking']) {
            $lines[] = 'Early morning waking (3-5am and cannot return to sleep): this is a classic symptom of depression and anxiety. Also caused by cortisol spike (stress), light entering room, or going to bed too early. If persistent, gently explore mood — early waking is often the first sign of depression.';
        }

        if ($ctx['poor_quality']) {
            $lines[] = 'Poor sleep quality despite adequate hours: likely causes are sleep apnea (check for snoring, gasping), alcohol (disrupts deep sleep), too warm room, or stress. Deep sleep (stages 3-4) is when the body repairs. Magnesium glycinate (200-400mg at night) significantly improves deep sleep quality. Room temperature 18-20°C is optimal.';
        }

        if ($ctx['racing_mind']) {
            $lines[] = 'Racing mind at bedtime: the brain needs a "shutdown ritual" to transition from active to sleep mode. Techniques: write down tomorrow\'s tasks before bed (offloads the brain), 4-7-8 breathing (inhale 4, hold 7, exhale 8 — activates parasympathetic system), body scan meditation, progressive muscle relaxation. Journaling 10 min before bed reduces bedtime anxiety significantly.';
        }

        if ($ctx['snoring']) {
            $lines[] = 'Snoring/sleep apnea: if snoring is loud and accompanied by gasping, choking, or witnessed breathing pauses — this is likely obstructive sleep apnea (OSA). OSA causes fragmented sleep, daytime fatigue, and long-term cardiovascular risk. Strongly advise seeing a doctor for sleep study. Weight loss helps significantly. Side sleeping reduces snoring. Do not dismiss this.';
        }

        if ($ctx['restless_legs']) {
            $lines[] = 'Restless legs syndrome: uncomfortable sensations in legs at night, urge to move. Often caused by iron deficiency (check ferritin), magnesium deficiency, or kidney issues. Iron supplementation helps if ferritin is low. Magnesium glycinate at night. Avoid caffeine and alcohol. Stretching legs before bed. If severe, see a neurologist.';
        }

        if ($ctx['nightmares']) {
            $lines[] = 'Nightmares/night terrors: often linked to stress, anxiety, PTSD, or certain medications. Imagery rehearsal therapy (rewriting the nightmare ending while awake) is evidence-based. Reducing stress before bed helps. If nightmares are frequent and distressing, suggest speaking to a mental health professional.';
        }

        // ── Root cause guidance ───────────────────────────────────
        if ($ctx['stress_related']) {
            $stressNote = 'Stress-related sleep issues: cortisol and adrenaline keep the brain alert. ';
            if ($ctx['stress_level']) {
                $stressNote .= "Known stress level: {$ctx['stress_level']}. ";
            }
            $stressNote .= 'Create a "worry time" — 15 min in the afternoon to write down worries and possible solutions. This prevents the brain from processing worries at bedtime. 4-7-8 breathing activates the vagus nerve and reduces cortisol within minutes.';
            $lines[] = $stressNote;
        }

        if ($ctx['screen_related']) {
            $lines[] = 'Screen/blue light: blue light from phones/laptops suppresses melatonin production for 2-3 hours. No screens 30-60 min before bed is the single most impactful sleep hygiene change. Use night mode/warm light after sunset. Keep phone outside the bedroom or face-down. Replace scrolling with reading (physical book), journaling, or light stretching.';
        }

        if ($ctx['caffeine_related']) {
            $lines[] = 'Caffeine and sleep: caffeine has a half-life of 5-7 hours — a 3pm chai still has 50% caffeine in your system at 8pm. Cut off caffeine by 1-2pm. Even "decaf" has some caffeine. Green tea has less caffeine but still affects sensitive people. Replace evening chai with herbal tea (chamomile, ashwagandha, tulsi).';
        }

        if ($ctx['late_eating']) {
            $lines[] = 'Late eating and sleep: heavy meals within 2-3 hours of sleep cause acid reflux, elevated body temperature, and disrupted sleep. Eat dinner by 7-8pm. If hungry later, a small protein snack (curd, handful of nuts) is fine — it actually stabilises blood sugar and improves sleep. Avoid spicy, fried, or very heavy food at night.';
        }

        if ($ctx['irregular_schedule']) {
            $lines[] = 'Irregular sleep schedule: the circadian rhythm (body clock) is set by consistent sleep and wake times. Varying by more than 1 hour disrupts the entire system. The wake time is more important than the sleep time — fix the wake time first, even on weekends. The body will naturally adjust sleep onset within 1-2 weeks.';
        }

        if ($ctx['environment']) {
            $lines[] = 'Sleep environment: optimal conditions are dark (blackout curtains or eye mask), cool (18-20°C), quiet (earplugs or white noise if noisy), and comfortable mattress/pillow. Even small amounts of light (phone LED, streetlight) suppress melatonin. The bedroom should be associated only with sleep and intimacy — not work or entertainment.';
        }

        // ── Health condition specific ─────────────────────────────
        if ($ctx['has_anxiety']) {
            $lines[] = 'Anxiety and sleep: anxiety and insomnia are bidirectional — each worsens the other. CBT-I (Cognitive Behavioural Therapy for Insomnia) is more effective than sleeping pills long-term. Key technique: stimulus control (bed = sleep only, not worry). Progressive muscle relaxation before bed. If anxiety is severe, suggest speaking to a mental health professional.';
        }

        if ($ctx['has_thyroid']) {
            $lines[] = 'Thyroid and sleep: hypothyroidism causes excessive sleepiness and poor quality sleep. Hyperthyroidism causes insomnia and racing heart at night. Ensure thyroid levels are controlled — sleep will not improve significantly until TSH is in range. Magnesium helps both conditions.';
        }

        if ($ctx['has_pcos']) {
            $lines[] = 'PCOS and sleep: women with PCOS have higher rates of sleep apnea and insomnia. Melatonin production is often disrupted. Consistent sleep schedule is especially important. Inositol (myo-inositol) improves sleep quality in PCOS. Avoid screens and bright light after 9pm.';
        }

        if ($ctx['has_diabetes']) {
            $lines[] = 'Diabetes and sleep: blood sugar fluctuations disrupt sleep. High sugar causes frequent urination at night. Low sugar causes night sweats and waking. Stable blood sugar = better sleep. Avoid high-carb dinner. Check sugar before bed — if below 120, have a small protein snack.';
        }

        if ($ctx['is_pregnant']) {
            $lines[] = 'Pregnancy and sleep: sleep disruption is common, especially in 1st trimester (fatigue) and 3rd trimester (discomfort, frequent urination). Best position: left side (improves blood flow to baby). Use a pregnancy pillow between knees. Short naps (20-30 min) are fine and beneficial. Avoid sleeping on back after 20 weeks.';
        }

        // ── Topic specific ────────────────────────────────────────
        if ($ctx['asking_about_routine']) {
            $lines[] = 'Sleep routine: the 30-60 min before bed is the "wind-down window." Sequence: dim lights → no screens → warm shower (drops body temperature after, inducing sleep) → light reading or journaling → 4-7-8 breathing → sleep. Same sequence every night trains the brain to associate the routine with sleep onset. Consistency of wake time is more important than bedtime.';
        }

        if ($ctx['asking_about_supplements']) {
            $suppNote = 'Sleep supplements (evidence-based): Magnesium glycinate (200-400mg at night) — improves deep sleep, reduces anxiety, safe long-term. Ashwagandha (KSM-66, 300mg at night) — reduces cortisol, improves sleep quality. Melatonin (0.5-1mg, 30 min before bed) — helps with sleep onset, especially for jet lag or shift work — use lowest effective dose. ';
            $suppNote .= 'Avoid: high-dose melatonin (>3mg), sleeping pills (habit-forming, suppress deep sleep). Chamomile tea and tulsi tea are gentle and safe.';
            $lines[] = $suppNote;
        }

        if ($ctx['asking_about_nap']) {
            $lines[] = 'Napping: a 20-min power nap (before 3pm) improves alertness without disrupting night sleep. Longer naps (>30 min) cause sleep inertia (grogginess) and reduce night sleep drive. If struggling with night sleep, avoid daytime naps entirely for 2 weeks — this builds sleep pressure and improves night sleep quality.';
        }

        if ($ctx['asking_about_hours']) {
            $lines[] = 'Sleep duration: adults need 7-9 hours. Quality matters more than quantity — 7 hours of deep sleep beats 9 hours of fragmented sleep. Sleep needs are individual — some people function well on 7 hours, others need 9. Signs of insufficient sleep: needing an alarm to wake up, feeling unrefreshed, falling asleep within 5 min of lying down, needing caffeine to function.';
        }

        // ── Age specific ──────────────────────────────────────────
        if ($ctx['age'] && $ctx['age'] >= 50) {
            $lines[] = 'Sleep changes with age (50+): sleep becomes lighter and more fragmented naturally. Earlier sleep and wake times are normal. More frequent night waking is common. Focus on sleep quality over quantity. Avoid napping if it disrupts night sleep. Melatonin production decreases with age — low-dose melatonin (0.5mg) can help.';
        }

        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Tailor sleep advice to their actual schedule.";
        }

        $block = "SLEEP COACHING CONTEXT (identify the specific sleep problem and its root cause — never give generic 'sleep hygiene' advice without addressing the actual issue):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
