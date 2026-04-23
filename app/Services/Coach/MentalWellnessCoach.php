<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class MentalWellnessCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $mentalContext   = $this->buildMentalContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $mentalContext);

        return parent::respond($user, $enrichedMessage, $sessionId);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTEXT BUILDER
    // ─────────────────────────────────────────────────────────────

    private function buildMentalContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // ── Primary emotional state detection ─────────────────────
        $context['feels_anxious']     = preg_match('/\b(anxious|anxiety|panic|panic attack|nervous|worry|worried|overthink|racing thoughts|heart racing|ghabra|darr|tension|dar lag raha)\b/i', $message);
        $context['feels_depressed']   = preg_match('/\b(depress|hopeless|empty|numb|no point|worthless|nothing matters|sad all time|udaas|nirash|akela|akeli|andhera|darkness inside)\b/i', $message);
        $context['feels_stressed']    = preg_match('/\b(stress|stressed|pressure|overwhelm|too much|can.t cope|breaking point|burnout|thak gaya|thak gayi|pareshan|sar dard stress)\b/i', $message);
        $context['feels_angry']       = preg_match('/\b(angry|anger|rage|furious|irritable|irritated|frustrated|gussa|krodh|chidchida|chidchidi|snap at)\b/i', $message);
        $context['feels_lonely']      = preg_match('/\b(lonely|alone|isolated|no one|no friends|no one understands|akela|akeli|koi nahi|koi samajhta nahi)\b/i', $message);
        $context['feels_low']         = preg_match('/\b(low|down|not okay|not good|feeling off|not myself|dull|flat|meh|theek nahi|acha nahi lag raha)\b/i', $message);
        $context['feels_grief']       = preg_match('/\b(grief|loss|lost someone|death|died|passed away|bereavement|mourning|gam|dukh|kho diya|mar gaye)\b/i', $message);
        $context['feels_guilty']      = preg_match('/\b(guilty|guilt|shame|ashamed|regret|my fault|blame myself|sharminda|pachtawa|galti meri)\b/i', $message);
        $context['feels_burnt_out']   = preg_match('/\b(burnout|burnt out|exhausted mentally|no energy for anything|done|finished|can.t anymore|toot gaya|toot gayi|hadd ho gayi)\b/i', $message);
        $context['feels_confused']    = preg_match('/\b(confused|lost|don.t know|no direction|what to do|purpose|meaning|samajh nahi|kya karoon|kya karun)\b/i', $message);

        // ── Severity signals ──────────────────────────────────────
        $context['high_severity']     = preg_match('/\b(can.t function|can.t get out of bed|stopped eating|stopped sleeping|can.t work|falling apart|completely lost|kuch nahi ho raha|bilkul nahi|bahut bura)\b/i', $message);
        $context['seeking_help']      = preg_match('/\b(need help|please help|don.t know what to do|help me|koi help karo|madad chahiye|kya karoon)\b/i', $message);
        $context['has_therapist']     = preg_match('/\b(therapist|therapy|counsellor|counseling|psychiatrist|psychologist|mental health professional|already seeing)\b/i', $message);
        $context['resistant_to_help'] = preg_match('/\b(don.t need therapy|therapy nahi|not that serious|just stressed|i.m fine|it.s nothing|theek hoon|kuch nahi)\b/i', $message);

        // ── Trigger / cause signals ───────────────────────────────
        $context['work_stress']       = preg_match('/\b(work|job|office|boss|colleague|deadline|workload|career|promotion|fired|resign|kaam|naukri|boss ne)\b/i', $message);
        $context['relationship_issue']= preg_match('/\b(relationship|partner|husband|wife|boyfriend|girlfriend|breakup|divorce|fight|argument|family issue|parents|in-laws|rishta|shaadi|pati|patni|ladka|ladki)\b/i', $message);
        $context['financial_stress']  = preg_match('/\b(money|financial|debt|loan|EMI|salary|afford|broke|paisa|paise nahi|karz|loan|EMI)\b/i', $message);
        $context['health_anxiety']    = preg_match('/\b(health anxiety|hypochondria|scared of illness|googling symptoms|what if i have|disease fear|bimari ka darr)\b/i', $message);
        $context['social_anxiety']    = preg_match('/\b(social anxiety|social situation|public speaking|meeting people|crowd|judged|embarrassed|log kya sochenge|log judge karenge)\b/i', $message);
        $context['exam_stress']       = preg_match('/\b(exam|study|marks|result|fail|board|competitive exam|UPSC|JEE|NEET|pariksha|padhai)\b/i', $message);
        $context['body_image']        = preg_match('/\b(body image|hate my body|ugly|fat|too thin|appearance|looks|weight shame|mota|patla|sundar nahi)\b/i', $message);

        // ── Coping pattern signals ────────────────────────────────
        $context['poor_sleep']        = preg_match('/\b(can.t sleep|insomnia|sleep deprived|nightmares|waking up anxious|neend nahi|raat ko uthna)\b/i', $message);
        $context['appetite_change']   = preg_match('/\b(not eating|overeating|no appetite|stress eating|binge|khana nahi|bahut kha raha|bhook nahi)\b/i', $message);
        $context['social_withdrawal'] = preg_match('/\b(avoiding people|don.t want to meet|staying home|isolating|log se milna nahi|ghar se nahi nikalna)\b/i', $message);
        $context['using_substances']  = preg_match('/\b(drinking more|alcohol to cope|smoking more|using drugs|substance|sharab zyada|peena badh gaya)\b/i', $message);

        // ── Topic signals ─────────────────────────────────────────
        $context['asking_about_anxiety_techniques'] = preg_match('/\b(how to calm|calm down|anxiety technique|breathing|grounding|panic attack help|kaise shant|shant kaise)\b/i', $message);
        $context['asking_about_therapy']            = preg_match('/\b(should i see|do i need therapy|therapist|counsellor|mental health help|kya therapy leni chahiye)\b/i', $message);
        $context['asking_about_meditation']         = preg_match('/\b(meditat|mindfulness|breathing exercise|pranayama|dhyan|calm technique)\b/i', $message);
        $context['asking_about_journaling']         = preg_match('/\b(journal|journaling|write|diary|express|likhna|diary likhna)\b/i', $message);
        $context['asking_about_self_care']          = preg_match('/\b(self care|take care of myself|me time|self love|apna khayal|apne liye)\b/i', $message);
        $context['asking_about_motivation']         = preg_match('/\b(no motivation|can.t do anything|no energy|what.s the point|why bother|kya fayda|mann nahi)\b/i', $message);

        // ── Health conditions linked to mental health ─────────────
        $healthCond = strtolower($memory['health_condition'] ?? '');
        $context['has_thyroid']  = str_contains($healthCond, 'thyroid') || str_contains($lower, 'thyroid');
        $context['has_pcos']     = str_contains($healthCond, 'pcos') || str_contains($lower, 'pcos');
        $context['has_diabetes'] = str_contains($healthCond, 'diabet') || str_contains($lower, 'diabet');

        // ── Memory context ────────────────────────────────────────
        $context['stress_level']  = $memory['stress_level']  ?? $user->stress_level ?? null;
        $context['sleep_pattern'] = $memory['sleep_pattern'] ?? null;
        $context['lifestyle']     = $memory['lifestyle']     ?? null;
        $context['challenges']    = $memory['challenges']    ?? null;
        $context['family_ctx']    = $memory['family_context'] ?? null;

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

        // ── Core framing — CRITICAL ───────────────────────────────
        $lines[] = 'You are a mental wellness coach — warm, non-judgmental, and deeply empathetic. ALWAYS acknowledge feelings FIRST before any advice or techniques. Never minimise what the user is feeling. You are NOT a therapist and cannot diagnose — but you can listen, validate, and offer evidence-based wellness tools. If severity is high, gently suggest professional support without pushing.';

        // ── Empathy-first response rules ──────────────────────────
        $lines[] = 'Response structure for emotional messages: 1) Acknowledge and validate the feeling specifically (not "I understand" — be specific to what they said). 2) Normalise if appropriate ("a lot of people feel this way, especially with..."). 3) Ask one gentle question OR offer one small practical tool. Never give a list of 5 tips when someone is in emotional pain.';

        // ── High severity — professional support ──────────────────
        if ($ctx['high_severity']) {
            $lines[] = 'User is showing HIGH SEVERITY signals — functioning is impaired. After acknowledging their feelings, gently mention that speaking to a mental health professional could really help. iCall (9152987821) and Vandrevala Foundation (1860-2662-345, 24/7) are free Indian helplines. Do this warmly, not clinically — "it might help to talk to someone who specialises in this."';
        }

        if ($ctx['seeking_help']) {
            $lines[] = 'User is actively seeking help — this is a positive sign. Respond with warmth and validation. They took a brave step by reaching out. Acknowledge that first.';
        }

        if ($ctx['has_therapist']) {
            $lines[] = 'User is already seeing a therapist/counsellor. Do not suggest therapy again — they are already getting professional support. Focus on complementary wellness tools (sleep, exercise, mindfulness, journaling) that support their therapy work.';
        }

        if ($ctx['resistant_to_help']) {
            $lines[] = 'User is minimising their struggles or resistant to professional help. Do not push therapy. Meet them where they are. Validate that what they are feeling is real even if they think it is "not that serious." Plant a gentle seed — "even talking to someone once can sometimes help clarify things."';
        }

        // ── Primary emotional state — specific guidance ───────────
        if ($ctx['feels_anxious']) {
            $lines[] = 'User is experiencing ANXIETY. Anxiety is the body\'s threat response — it is not weakness. Validate first. Practical tools: 4-7-8 breathing (inhale 4, hold 7, exhale 8) activates parasympathetic nervous system within 90 seconds. 5-4-3-2-1 grounding (5 things you see, 4 hear, 3 touch, 2 smell, 1 taste) interrupts panic. Box breathing (4-4-4-4) for sustained calm. Do not tell them to "just relax" — it does not work.';
        }

        if ($ctx['feels_depressed']) {
            $lines[] = 'User may be experiencing DEPRESSION symptoms. This is serious — respond with deep empathy and zero judgment. Depression is not laziness or weakness — it is a medical condition. Do not say "think positive" or "others have it worse." Validate the heaviness. Behavioural activation (small actions, not motivation) is the evidence-based approach — even getting out of bed and sitting in sunlight for 5 min is a win. If symptoms are persistent (2+ weeks), gently suggest professional support.';
        }

        if ($ctx['feels_stressed']) {
            $stressNote = 'User is STRESSED. ';
            if ($ctx['stress_level']) {
                $stressNote .= "Known stress level: {$ctx['stress_level']}. ";
            }
            $stressNote .= 'Stress is the gap between demands and perceived resources. Two approaches: reduce demands (boundaries, saying no, delegating) OR increase resources (sleep, exercise, social support, mindfulness). Ask which feels more relevant to their situation before suggesting.';
            $lines[] = $stressNote;
        }

        if ($ctx['feels_angry']) {
            $lines[] = 'User is experiencing ANGER or irritability. Anger is a secondary emotion — there is usually hurt, fear, or injustice underneath. Validate the anger first ("that sounds genuinely frustrating"). Do not tell them to calm down. Practical tools: physical release (walk, exercise), delay response by 10 min before reacting, identify the underlying feeling. Chronic irritability can also be a sign of depression or burnout — explore gently.';
        }

        if ($ctx['feels_lonely']) {
            $lines[] = 'User feels LONELY or isolated. Loneliness is one of the most painful human experiences — validate it fully. Do not immediately suggest "join a club" — that feels dismissive. Explore: is it situational (new city, life change) or chronic? Small steps: one meaningful conversation per day, reconnecting with one old friend, online communities with shared interests. Quality of connection matters more than quantity.';
        }

        if ($ctx['feels_grief']) {
            $lines[] = 'User is experiencing GRIEF or loss. Grief has no timeline and no right way to feel. Do not say "they are in a better place" or "time heals everything." Simply be present: "I am so sorry for your loss. That is an enormous pain to carry." Grief comes in waves — some days are harder than others and that is normal. Grief counselling can be very helpful — mention gently if appropriate.';
        }

        if ($ctx['feels_guilty']) {
            $lines[] = 'User is experiencing GUILT or shame. Distinguish between healthy guilt (signals a values violation, motivates repair) and toxic shame (attacks identity — "I am bad"). Validate the feeling without reinforcing the self-attack. Cognitive reframe: "You did something you regret — that does not make you a bad person. What would you tell a close friend in this situation?"';
        }

        if ($ctx['feels_burnt_out']) {
            $lines[] = 'User is BURNT OUT. Burnout is not just tiredness — it is emotional exhaustion, depersonalisation, and reduced sense of accomplishment. Recovery requires rest (not just sleep — genuine psychological rest), boundary-setting, and reconnecting with meaning. Ask: "What used to give you energy that you have stopped doing?" Burnout recovery takes weeks to months — set realistic expectations.';
        }

        if ($ctx['feels_confused']) {
            $lines[] = 'User feels LOST or without direction. This is often an identity or values question, not a practical one. Journaling prompts can help: "What matters most to me?" "What would I do if I knew I could not fail?" "What am I tolerating that I should not be?" Do not rush to give answers — help them find their own.';
        }

        // ── Trigger-specific context ──────────────────────────────
        if ($ctx['work_stress']) {
            $lines[] = 'Work-related stress: the most common source of mental health issues in India. Key areas: workload (is it sustainable?), control (do they have any autonomy?), relationships (toxic boss/colleagues?), recognition (is effort acknowledged?). Practical: clear work-life boundaries, "shutdown ritual" at end of workday, not checking work messages after hours. If workplace is toxic, validate that leaving is a valid option.';
        }

        if ($ctx['relationship_issue']) {
            $familyCtx = $ctx['family_ctx'] ? " Known family context: {$ctx['family_ctx']}." : '';
            $lines[] = "Relationship/family stress detected.{$familyCtx} Indian family dynamics add unique pressure — joint families, arranged marriage expectations, parental pressure. Validate without taking sides. Focus on: what they can control (their response, their boundaries), communication skills (I-statements: 'I feel X when Y happens'), and whether the relationship is safe. For serious relationship issues, couples counselling or individual therapy is appropriate.";
        }

        if ($ctx['financial_stress']) {
            $lines[] = 'Financial stress: money stress is one of the top causes of anxiety and depression. Validate — financial pressure is real and heavy. Do not give financial advice (outside scope). Focus on the emotional side: the anxiety, the shame, the fear. Practical mental health tools: separate "what I can control" from "what I cannot," avoid catastrophising ("I will always be broke"), one small action per day.';
        }

        if ($ctx['health_anxiety']) {
            $lines[] = 'Health anxiety detected. The more someone Googles symptoms, the more anxious they become — Google always shows worst-case scenarios. Validate the fear (it comes from caring about health) but gently challenge the pattern. Suggest: set a "worry time" (15 min/day to worry, then stop), avoid symptom-checking outside that time, see a doctor for reassurance rather than Google.';
        }

        if ($ctx['social_anxiety']) {
            $lines[] = 'Social anxiety: fear of judgment is one of the most common anxiety types. Validate — it is genuinely uncomfortable. CBT approach: the feared outcome (embarrassment, judgment) is usually overestimated, and the ability to cope is underestimated. Gradual exposure (small social situations first) is the evidence-based treatment. Breathing before social situations helps. Remind: most people are too focused on themselves to judge others as harshly as we fear.';
        }

        if ($ctx['exam_stress']) {
            $lines[] = 'Exam/academic stress: extremely common in India given competitive pressure. Validate the pressure — it is real. Practical: break study into small chunks (Pomodoro), adequate sleep is non-negotiable for memory consolidation, exercise improves focus, avoid all-nighters (they impair performance). Perspective: one exam does not define a life. If anxiety is severe, breathing techniques before exams help significantly.';
        }

        if ($ctx['body_image']) {
            $lines[] = 'Body image issues: respond with extra sensitivity — never comment on weight or appearance. Validate that body image struggles are painful and very common, especially with social media. Focus on body function over appearance ("your body does so much for you"). Avoid diet culture language. If disordered eating is suspected, gently suggest speaking to a professional.';
        }

        // ── Coping pattern concerns ───────────────────────────────
        if ($ctx['poor_sleep']) {
            $lines[] = 'Poor sleep and mental health are bidirectional — each worsens the other. Sleep deprivation amplifies anxiety and depression by 30-40%. Prioritise sleep hygiene: consistent sleep time, dark/cool room, no screens 30 min before bed, no caffeine after 2pm. If anxiety is causing sleep issues, progressive muscle relaxation or body scan meditation before bed is effective.';
        }

        if ($ctx['appetite_change']) {
            $lines[] = 'Appetite changes (not eating or overeating) are common mental health symptoms. Validate — when we are struggling emotionally, eating is often the first thing affected. Gentle guidance: even small amounts of food help stabilise mood (blood sugar affects mood significantly). If stress eating, explore the emotional trigger rather than the food itself.';
        }

        if ($ctx['social_withdrawal']) {
            $lines[] = 'Social withdrawal is both a symptom and a worsening factor for depression and anxiety. Isolation feels safe but deepens the problem. Gentle approach: do not push socialising. Suggest micro-connections (a text to one person, a brief call) rather than big social events. Even brief positive interactions significantly improve mood.';
        }

        if ($ctx['using_substances']) {
            $lines[] = 'Increased alcohol/substance use as coping: validate that people reach for relief when in pain — it makes sense in the short term. But alcohol is a depressant and worsens anxiety and depression over time. Gently explore: "What are you trying to feel or not feel when you drink?" Suggest healthier coping alternatives. If dependence is suspected, professional support is important.';
        }

        // ── Technique requests ────────────────────────────────────
        if ($ctx['asking_about_anxiety_techniques']) {
            $lines[] = 'Anxiety techniques (evidence-based): 1) 4-7-8 breathing — inhale 4 counts, hold 7, exhale 8. Activates vagus nerve. 2) 5-4-3-2-1 grounding — interrupts panic by engaging senses. 3) Box breathing — 4 counts each side. 4) Cold water on face/wrists — activates dive reflex, slows heart rate. 5) Progressive muscle relaxation — tense and release each muscle group. Suggest starting with the simplest one.';
        }

        if ($ctx['asking_about_therapy']) {
            $lines[] = 'Therapy guidance: therapy is not only for severe cases — it is for anyone who wants to understand themselves better or cope more effectively. CBT (Cognitive Behavioural Therapy) is the most evidence-based for anxiety and depression. In India: iCall (free, trained counsellors), YourDOST, Vandrevala Foundation (24/7 free). Online therapy (BetterHelp, Wysa) is accessible and affordable. Normalise it — seeing a therapist is like going to the gym for your mind.';
        }

        if ($ctx['asking_about_meditation']) {
            $lines[] = 'Meditation for mental wellness: start with 5 minutes, not 20. Guided meditation apps (Headspace, Calm, Insight Timer — free) are easier for beginners than silent meditation. Body scan meditation is excellent for anxiety. Loving-kindness meditation (metta) helps with loneliness and self-criticism. Pranayama (Anulom Vilom, Bhramari) is highly effective for anxiety — rooted in Indian tradition.';
        }

        if ($ctx['asking_about_journaling']) {
            $lines[] = 'Journaling for mental health: highly effective for processing emotions and reducing rumination. Prompts: "What am I feeling right now and where do I feel it in my body?" "What is the worst that could happen, and could I handle it?" "What am I grateful for today (even small things)?" "What do I need right now?" Even 5 minutes of free writing reduces cortisol. No rules — it does not need to be neat or coherent.';
        }

        if ($ctx['asking_about_self_care']) {
            $lines[] = 'Self-care is not bubble baths — it is the basics: sleep, movement, nutrition, connection, and rest. True self-care means doing things that genuinely restore you, not just distract you. Ask: "What actually makes you feel better the next day (not just in the moment)?" Distinguish between numbing (scrolling, alcohol) and restoring (sleep, nature, meaningful conversation).';
        }

        if ($ctx['asking_about_motivation']) {
            $lines[] = 'Low motivation and mental health: when depressed or burnt out, motivation does not come before action — action comes first, then motivation follows. Behavioural activation: do the smallest possible version of something (get dressed, step outside for 2 min). Do not wait to feel ready. The brain rewards action with dopamine, which creates motivation. Start with one tiny thing.';
        }

        // ── Health conditions and mental health link ──────────────
        if ($ctx['has_thyroid']) {
            $lines[] = 'Thyroid and mental health: hypothyroidism commonly causes depression, anxiety, brain fog, and low motivation. If mental health symptoms are new or worsening, ask if thyroid levels have been checked recently. Undertreated hypothyroidism can mimic depression. This is a medical issue, not a character flaw.';
        }

        if ($ctx['has_pcos']) {
            $lines[] = 'PCOS and mental health: women with PCOS have significantly higher rates of anxiety and depression due to hormonal imbalances and the emotional burden of the condition. Validate this connection — it is not "just in their head." Exercise, sleep, and stress management are especially important for PCOS mental health.';
        }

        if ($ctx['has_diabetes']) {
            $lines[] = 'Diabetes and mental health: diabetes distress (worry about managing the condition) and depression are very common in diabetics. Blood sugar fluctuations directly affect mood — low sugar causes anxiety and irritability, high sugar causes fatigue and low mood. Managing blood sugar is also managing mental health.';
        }

        // ── Gender and age specific ───────────────────────────────
        if ($ctx['gender'] === 'female') {
            $lines[] = 'Female user: hormonal fluctuations (menstrual cycle, PCOS, perimenopause) significantly affect mood and mental health. Premenstrual dysphoric disorder (PMDD) causes severe mood changes before periods. Postpartum depression affects 1 in 5 new mothers. These are medical conditions, not weakness. Validate the hormonal component if relevant.';
        }

        if ($ctx['age'] && $ctx['age'] >= 40 && $ctx['gender'] === 'female') {
            $lines[] = 'Perimenopause (40+): can cause anxiety, mood swings, depression, and brain fog even before periods stop. Often misdiagnosed as "just stress." If mental health symptoms are new after 40, hormonal changes may be a factor worth discussing with a doctor.';
        }

        if ($ctx['age'] && $ctx['age'] <= 25) {
            $lines[] = 'Young user (under 25): the brain is still developing until age 25. Identity struggles, social comparison, academic/career pressure, and relationship issues are especially intense at this age. Validate that this is a genuinely hard period of life. Social media comparison is a significant mental health risk factor for this age group.';
        }

        // ── Lifestyle context ─────────────────────────────────────
        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Tailor mental wellness suggestions to fit their actual life — not an ideal one.";
        }

        if ($ctx['challenges']) {
            $lines[] = "Known challenges: {$ctx['challenges']}. These are real barriers — acknowledge them rather than giving advice that ignores their situation.";
        }

        // ── Build final block ─────────────────────────────────────
        $block = "MENTAL WELLNESS COACHING CONTEXT (lead with empathy always — validate before advising — never minimise feelings):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
