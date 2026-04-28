<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;
use App\Models\UserStreak;

class HabitCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId, string $inputMode = 'chat'): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $habitContext    = $this->buildHabitContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $habitContext);

        return parent::respond($user, $enrichedMessage, $sessionId, $inputMode);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTEXT BUILDER
    // ─────────────────────────────────────────────────────────────

    private function buildHabitContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // ── Streak data ───────────────────────────────────────────
        $streak = UserStreak::where('user_id', $user->id)->first();
        $context['current_streak']  = $streak?->current_streak  ?? 0;
        $context['longest_streak']  = $streak?->longest_streak  ?? 0;
        $context['streak_broken']   = $streak && $streak->current_streak === 0 && $streak->longest_streak > 0;

        // ── Habit type signals ────────────────────────────────────
        $context['about_morning_routine'] = preg_match('/\b(morning|subah|wake up|uthna|morning routine|start day|early|alarm)\b/i', $message);
        $context['about_night_routine']   = preg_match('/\b(night|raat|bedtime|sleep routine|before bed|evening routine|wind down)\b/i', $message);
        $context['about_diet_habit']      = preg_match('/\b(eating habit|food habit|diet habit|healthy eating|meal habit|khana|stop eating|sugar habit|junk food)\b/i', $message);
        $context['about_exercise_habit']  = preg_match('/\b(exercise habit|workout habit|gym habit|walk habit|active|movement habit|fitness routine)\b/i', $message);
        $context['about_sleep_habit']     = preg_match('/\b(sleep habit|sleep schedule|bedtime|consistent sleep|sleep time|neend|sone ki aadat)\b/i', $message);
        $context['about_water_habit']     = preg_match('/\b(water habit|drink water|hydration habit|pani peena|water intake)\b/i', $message);
        $context['about_screen_habit']    = preg_match('/\b(screen time|phone habit|social media|scrolling|phone addiction|mobile|reduce screen)\b/i', $message);
        $context['about_reading_habit']   = preg_match('/\b(reading|book|padhna|learn|study habit|reading habit)\b/i', $message);
        $context['about_meditation']      = preg_match('/\b(meditat|mindfulness|breathing|pranayama|dhyan|calm|mental habit)\b/i', $message);
        $context['about_productivity']    = preg_match('/\b(productive|productivity|focus|deep work|distract|procrastinat|time management|schedule|plan)\b/i', $message);

        // ── Struggle signals ──────────────────────────────────────
        $context['cant_be_consistent']  = preg_match('/\b(consistent|consistency|can.t stick|always fail|give up|quit|start again|restart|baar baar|phir se)\b/i', $message);
        $context['no_motivation']        = preg_match('/\b(no motivat|lazy|no mood|don.t feel like|not feeling|mann nahi|ichha nahi|boring|dull)\b/i', $message);
        $context['too_busy']             = preg_match('/\b(busy|no time|time nahi|hectic|schedule full|work too much|no time for)\b/i', $message);
        $context['forgot_habit']         = preg_match('/\b(forgot|forget|bhool|bhool gaya|bhool gayi|missed|skip|miss kiya)\b/i', $message);
        $context['overwhelmed']          = preg_match('/\b(overwhelm|too much|bahut zyada|don.t know where|where to start|confused|so many things)\b/i', $message);
        $context['broke_streak']         = preg_match('/\b(broke|break|streak break|missed day|gap|ruined|failed|nahi kiya|kal nahi)\b/i', $message);
        $context['relapsed_bad_habit']   = preg_match('/\b(relapse|went back|again|phir se|old habit|bad habit back|couldn.t resist|gave in)\b/i', $message);

        // ── Bad habit breaking signals ────────────────────────────
        $context['wants_to_quit_sugar']   = preg_match('/\b(quit sugar|stop sugar|sugar addiction|sweet craving|mithai|chocolate craving|sugar free)\b/i', $message);
        $context['wants_to_quit_junk']    = preg_match('/\b(quit junk|stop junk|junk food habit|fast food habit|chips|biscuit habit|namkeen)\b/i', $message);
        $context['wants_to_quit_smoking'] = preg_match('/\b(quit smoking|stop smoking|cigarette|beedi|tobacco|nicotine|smoking habit)\b/i', $message);
        $context['wants_to_quit_alcohol'] = preg_match('/\b(quit alcohol|stop drinking|alcohol habit|drink less|reduce alcohol|sharab)\b/i', $message);
        $context['wants_to_quit_phone']   = preg_match('/\b(phone addiction|reduce phone|less screen|social media addiction|quit instagram|quit youtube|doom scroll)\b/i', $message);
        $context['wants_to_quit_late_night'] = preg_match('/\b(late night|stop staying up|sleep early|early to bed|raat ko late|night owl)\b/i', $message);

        // ── Topic signals ─────────────────────────────────────────
        $context['asking_how_to_start']   = preg_match('/\b(how to start|kaise shuru|where to begin|first step|getting started|new habit|build habit)\b/i', $message);
        $context['asking_about_streaks']  = preg_match('/\b(streak|days|how many days|track|progress|maintain|keep going|kitne din)\b/i', $message);
        $context['asking_about_triggers'] = preg_match('/\b(trigger|cue|reminder|what makes|why do i|habit loop|automatic|without thinking)\b/i', $message);
        $context['asking_about_rewards']  = preg_match('/\b(reward|treat|celebrate|incentive|motivation|prize|feel good|achievement)\b/i', $message);
        $context['asking_for_plan']       = preg_match('/\b(plan|routine|schedule|habit plan|daily plan|weekly plan|structure|system)\b/i', $message);
        $context['asking_about_identity'] = preg_match('/\b(identity|who i am|i am the type|become|transform|change myself|new me|better person)\b/i', $message);

        // ── Memory context ────────────────────────────────────────
        $context['lifestyle']   = $memory['lifestyle']      ?? null;
        $context['challenges']  = $memory['challenges']     ?? null;
        $context['main_goal']   = $memory['main_goal']      ?? null;
        $context['stress']      = $memory['stress_level']   ?? $user->stress_level ?? null;
        $context['sleep']       = $memory['sleep_pattern']  ?? null;
        $context['activity']    = $memory['activity_level'] ?? $user->activity_level ?? null;

        // ── Physical stats ────────────────────────────────────────
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

        // ── Core framing ──────────────────────────────────────────
        $lines[] = 'You are a habit and behaviour change coach. Use behaviour science principles (habit loop, identity-based habits, implementation intentions, temptation bundling). Never lecture — guide with empathy and practical small steps. The goal is sustainable change, not perfection.';

        // ── Streak context ────────────────────────────────────────
        if ($ctx['current_streak'] > 0) {
            $lines[] = "User has a current streak of {$ctx['current_streak']} days. Acknowledge this positively — streaks build momentum. Encourage them to protect it.";
        }

        if ($ctx['longest_streak'] > 0 && $ctx['streak_broken']) {
            $lines[] = "User broke their streak (longest was {$ctx['longest_streak']} days). Do NOT make them feel bad. Reframe: missing once is an accident, missing twice is starting a new habit. The goal is to get back on track today, not tomorrow.";
        }

        if ($ctx['current_streak'] >= 21) {
            $lines[] = "User has maintained a {$ctx['current_streak']}-day streak — this is significant. At 21 days the habit is forming neurologically. At 66 days it becomes automatic. Celebrate this milestone and encourage them to push to the next milestone.";
        }

        // ── Struggle handling — empathy first ─────────────────────
        if ($ctx['cant_be_consistent']) {
            $lines[] = 'Consistency struggle: the problem is usually the habit is too big or too vague. Apply the 2-minute rule — shrink the habit to its smallest possible version (e.g. "exercise" becomes "put on workout clothes"). Never miss twice in a row — this is the most important rule. Consistency beats perfection every time.';
        }

        if ($ctx['no_motivation']) {
            $lines[] = 'No motivation: motivation follows action, not the other way around. You do not wait to feel motivated — you act first, then motivation comes. Suggest: start with the smallest possible action. Use temptation bundling — pair the habit with something enjoyable (e.g. only listen to favourite podcast while walking). Design the environment to make the habit easier.';
        }

        if ($ctx['too_busy']) {
            $busyNote = 'User is too busy. ';
            if ($ctx['lifestyle']) {
                $busyNote .= "Known lifestyle: {$ctx['lifestyle']}. ";
            }
            $busyNote .= 'Solution: habit stacking — attach new habit to an existing one (e.g. "after I brush my teeth, I will do 5 squats"). Identify 5-10 min windows in their day. The habit does not need to be long — it needs to be consistent. A 5-min walk every day beats a 1-hour walk once a week.';
            $lines[] = $busyNote;
        }

        if ($ctx['forgot_habit']) {
            $lines[] = 'User forgot their habit. Solution: implementation intention — be specific about WHEN and WHERE (e.g. "I will drink water at 8am, 12pm, 4pm, 8pm"). Use phone reminders. Visual cues — put water bottle on desk, put gym shoes by the door. The environment should prompt the habit, not willpower.';
        }

        if ($ctx['overwhelmed']) {
            $lines[] = 'User is overwhelmed — trying to change too many things at once. This is the most common habit mistake. Solution: pick ONE habit to focus on for 30 days. Master it before adding another. Ask: "If you could only change one thing this month, what would have the biggest impact on your life?" Start there.';
        }

        if ($ctx['broke_streak']) {
            $lines[] = 'Streak was broken. IMPORTANT: respond with compassion, not disappointment. Missing one day does not erase progress — the neural pathways are still there. The only rule that matters: never miss twice. Ask what got in the way and help them solve that specific obstacle for next time.';
        }

        if ($ctx['relapsed_bad_habit']) {
            $lines[] = 'User relapsed into a bad habit. This is normal and expected in behaviour change — it is part of the process, not failure. Average person tries to quit a habit 8-10 times before succeeding. Ask: what triggered it? Help them identify the cue and plan a specific response for next time. Progress is not linear.';
        }

        // ── Bad habit breaking intelligence ──────────────────────
        if ($ctx['wants_to_quit_sugar']) {
            $lines[] = 'Quitting sugar: cold turkey is harder than gradual reduction. Strategy: reduce by 25% each week. Replace sweet cravings with fruit (natural sugar + fibre slows absorption), dates, dark chocolate (70%+). Identify triggers (stress eating, boredom, post-meal habit). The craving lasts only 10-15 minutes — suggest a distraction technique (walk, water, brush teeth).';
        }

        if ($ctx['wants_to_quit_junk']) {
            $lines[] = 'Quitting junk food: do not rely on willpower — remove it from the environment. If it is not in the house, you cannot eat it. Replace with healthy alternatives that satisfy the same craving (roasted chana instead of chips, makhana instead of namkeen, fruit instead of biscuits). Meal prep on weekends reduces weekday junk food decisions.';
        }

        if ($ctx['wants_to_quit_smoking']) {
            $lines[] = 'Quitting smoking: this requires medical support for most people — nicotine replacement therapy (patches, gum) doubles success rates. Identify smoking triggers (after meals, stress, with chai). Replace the ritual (not just the cigarette) — deep breathing, chewing gum, or a short walk after meals. Suggest consulting a doctor for NRT or medication support.';
        }

        if ($ctx['wants_to_quit_alcohol']) {
            $lines[] = 'Reducing alcohol: identify triggers (social situations, stress, habit with dinner). Replace with a ritual (sparkling water with lime, mocktail, herbal tea). Tell close friends/family — social accountability is powerful. If dependence is suspected, medical supervision for withdrawal is important — do not suggest cold turkey for heavy drinkers.';
        }

        if ($ctx['wants_to_quit_phone']) {
            $lines[] = 'Reducing phone/screen time: use phone\'s built-in screen time limits. Remove social media apps from home screen — friction reduces usage. Designate phone-free times (meals, first 30 min of morning, last 30 min before bed). Replace scrolling with a physical activity (book, walk, journaling). Grayscale mode reduces dopamine reward from screen.';
        }

        if ($ctx['wants_to_quit_late_night']) {
            $lines[] = 'Fixing late-night habit: the body follows light cues — dim lights after 9pm, avoid blue screens. Set a "wind-down alarm" 30 min before target sleep time. Create a consistent pre-sleep ritual (same sequence every night trains the brain). Identify what keeps them up (phone, work, anxiety) and address that specifically.';
        }

        // ── Habit type specific guidance ──────────────────────────
        if ($ctx['about_morning_routine']) {
            $lines[] = 'Morning routine: the first 30-60 min sets the tone for the day. Ideal sequence: no phone for first 10 min, 2 glasses water, 5-10 min movement or stretching, light breakfast. Do not try to build a 2-hour morning routine overnight — start with one anchor habit and build from there. Consistency of time matters more than what you do.';
        }

        if ($ctx['about_night_routine']) {
            $lines[] = 'Night routine: the night routine determines morning energy. Key habits: consistent sleep time (even weekends), no screens 30 min before bed, dim lights, write 3 things done well today (positive reflection reduces anxiety), prepare tomorrow\'s clothes/bag (reduces morning friction). Temperature: cooler room (18-20°C) improves sleep quality.';
        }

        if ($ctx['about_diet_habit']) {
            $lines[] = 'Diet habit change: do not overhaul the entire diet at once. Pick one change (e.g. add vegetables to lunch, replace evening biscuits with fruit). Use the "addition not subtraction" approach — add healthy foods first, crowding out unhealthy ones naturally. Meal prep Sunday reduces weekday bad food decisions by 70%.';
        }

        if ($ctx['about_exercise_habit']) {
            $lines[] = 'Exercise habit: the biggest barrier is starting. Solution: reduce friction to zero — sleep in workout clothes, keep shoes by the door, have a default 10-min workout for low-energy days. Schedule it like a meeting. Find a time that works with their natural energy (morning people vs evening people). The habit is showing up — the workout quality comes later.';
        }

        if ($ctx['about_water_habit']) {
            $lines[] = 'Water habit: most people are chronically dehydrated without knowing it. Strategy: drink 2 glasses immediately on waking (before phone), keep a 1L bottle visible on desk, drink 1 glass before each meal. Link water to existing habits (after brushing teeth, before each meal, when phone rings). Target: 2.5-3L/day.';
        }

        if ($ctx['about_meditation']) {
            $lines[] = 'Meditation habit: start with 5 minutes, not 20. Apps like Headspace or Insight Timer help beginners. Best time: morning before checking phone, or before sleep. Even 5 min of focused breathing (4 counts in, hold 4, out 6) activates the parasympathetic nervous system and reduces cortisol. Consistency of 5 min daily beats occasional 30-min sessions.';
        }

        if ($ctx['about_productivity']) {
            $lines[] = 'Productivity habits: time-blocking is more effective than to-do lists. Identify the 1-3 most important tasks each day (MIT — Most Important Tasks). Work in 90-min focused blocks (ultradian rhythm). Pomodoro (25 min work, 5 min break) for tasks requiring sustained focus. Protect the first 2 hours of the day for deep work — no meetings, no email.';
        }

        // ── Topic-specific behaviour science ─────────────────────
        if ($ctx['asking_how_to_start']) {
            $lines[] = 'How to start a new habit: 1) Make it obvious (cue — put running shoes by door). 2) Make it attractive (temptation bundle with something enjoyable). 3) Make it easy (2-minute rule — start with smallest version). 4) Make it satisfying (immediate reward — track it, celebrate small wins). These are the 4 laws of behaviour change from Atomic Habits.';
        }

        if ($ctx['asking_about_streaks']) {
            $lines[] = 'Streaks and tracking: visual progress (habit tracker, calendar X method) creates a "do not break the chain" motivation. But streaks should not become the goal — the behaviour is the goal. Missing one day is okay. Missing two days starts a new (bad) habit. Suggest: track in a simple notebook or habit app. Review weekly, not daily.';
        }

        if ($ctx['asking_about_triggers']) {
            $lines[] = 'Habit triggers (cues): every habit has a cue → routine → reward loop. To build a habit: identify a reliable cue (time, location, preceding action, emotional state). To break a habit: identify and disrupt the cue. Implementation intention: "When [CUE], I will [HABIT]" — this specific format doubles habit follow-through rates in research.';
        }

        if ($ctx['asking_about_rewards']) {
            $lines[] = 'Rewards and reinforcement: the brain needs an immediate reward to encode a habit. Create a small celebration after each habit completion (fist pump, say "yes", check it off). The reward must be immediate — future benefits (health, weight loss) are too abstract for the habit loop. Over time, the habit itself becomes rewarding.';
        }

        if ($ctx['asking_for_plan']) {
            $planNote = 'Habit plan: ';
            if ($ctx['main_goal']) {
                $planNote .= "User's main goal: {$ctx['main_goal']}. ";
            }
            $planNote .= 'Build a habit stack around 3 anchor habits: morning (sets the day), midday (maintains momentum), evening (prepares for tomorrow). Each habit should be specific, small, and linked to an existing routine. Review and adjust after 2 weeks. Do not add new habits until current ones are automatic (usually 4-6 weeks).';
            $lines[] = $planNote;
        }

        if ($ctx['asking_about_identity']) {
            $lines[] = 'Identity-based habits (most powerful approach): instead of "I want to exercise" → "I am someone who moves every day." Every action is a vote for the identity you want. Ask: "What would a healthy person do right now?" Small wins build evidence for the new identity. The goal is not to run a marathon — it is to become a runner.';
        }

        // ── Lifestyle and challenge context ──────────────────────
        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. Address these specific obstacles — do not give generic advice that ignores their real barriers.";
        }

        if ($ctx['stress'] && in_array($ctx['stress'], ['high', 'medium'])) {
            $lines[] = "User has {$ctx['stress']} stress. High stress depletes willpower and makes habit formation harder. Prioritise stress-reducing habits first (sleep, short walks, breathing). When stressed, habits regress to the most automatic ones — this is why building strong foundations matters.";
        }

        // ── Age context ───────────────────────────────────────────
        if ($ctx['age'] && $ctx['age'] >= 40) {
            $lines[] = 'User 40+: habits are more deeply ingrained but also more stable once formed. Change takes longer but lasts longer. Focus on replacing old habits rather than just stopping them — the neural pathway needs a new route, not just a roadblock.';
        }

        // ── Build final block ─────────────────────────────────────
        $block = "HABIT COACHING CONTEXT (use behaviour science naturally — make it feel like a supportive conversation, not a lecture):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
