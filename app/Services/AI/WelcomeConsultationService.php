<?php

namespace App\Services\AI;

use App\Jobs\GenerateConsultationReport;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\NLP\LanguageDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;
use Illuminate\Support\Facades\Log;

class WelcomeConsultationService
{
    public const MIN_USER_TURNS = 4;

    private const HISTORY_WINDOW     = 20;
    private const EMOTIONAL_THRESHOLD = -0.6;

    // Fields are detected from USER messages only.
    // Self-negating fields (activity, sleep, stress) count even when negated — "no exercise" is a valid answer.
    private const SELF_NEGATING_FIELDS = ['activity', 'sleep', 'stress'];

    private const NEGATION_PREFIXES = [
        'nahi ', 'nahin ', 'no ', 'not ', "don't ", 'dont ',
        'do not ', 'never ', 'without ', 'koi nahi ', 'bilkul nahi ',
        'abhi nahi ', 'filhaal nahi ',
    ];

    private const FIELD_SIGNALS = [

        'condition_detail' => [
            'type 1', 'type 2', 'type1', 'type2', 't1d', 't2d', 'type one', 'type two',
            'type ek', 'type do', 'insulin leta', 'insulin leti', 'insulin pe hoon', 'on insulin',
            'metformin', 'glipizide', 'januvia', 'jardiance', 'gestational', 'prediabet',
            'borderline sugar', 'borderline diabetes', 'saal se hai', 'saal se chal',
            'years se hai', 'years ago diagnosed', 'diagnosed with', 'pata chala',
            'sugar hai', 'diabetes hai', 'fasting sugar', 'sugar level', 'hba1c',
            'pp sugar', 'random sugar', 'blood sugar', 'i have type', 'i have diabetes',
            'i am diabetic', 'been diabetic', 'saal se', 'sal se', 'months se',
            'mahine se', 'since last', 'since 20', 'since 19',
            'for 1 year', 'for 2 year', 'for 3 year', 'for 5 year', 'for 10 year',
            'ek saal', 'do saal', 'teen saal',
        ],

        'cycle' => [
            'cycle regular', 'cycle irregular', 'period regular', 'period irregular',
            'periods aate hain', 'periods nahi aate', 'missed period', 'late period',
            'irregular periods', 'cycle skip', 'period skip', 'period late',
            'menses', 'menstrual', 'periods bahut', 'period pain', 'cramps', 'spotting',
            'my periods are', 'my cycle is', 'periods come', 'periods dont',
            'regular hai', 'irregular hai', 'regular hain', 'irregular hain',
            'regular rehta', 'irregular rehta', '28 days', '30 days', '35 days',
            'every month', 'cycle 28', 'cycle 30', 'cycle 35',
        ],

        'medication' => [
            'thyroxine', 'eltroxin', 'thyronorm', 'levothyroxine',
            'thyroid medicine', 'thyroid tablet', 'thyroid ki dawa',
            'thyroid medication', 'no medicine for thyroid', 'koi thyroid medicine nahi',
            'tsh level', 'tsh hai', 'tsh kitna', 'tsh normal',
            'hypothyroid', 'hyperthyroid', 'i take thyroid', 'on thyroid meds',
            'medicine le raha', 'medicine le rahi', 'dawa le raha', 'dawa le rahi',
            'tablet le raha', 'tablet le rahi', 'medicine chal rahi',
            'haan medicine', 'yes medicine', 'no medicine', 'koi medicine nahi',
            'medicine nahi', 'dawa nahi', 'without medicine', 'bina medicine',
        ],

        'stage' => [
            'month pregnant', 'trimester', 'hafte pregnant', 'weeks pregnant',
            'due date', 'delivery date', 'pehla mahina', 'doosra mahina',
            'first trimester', 'second trimester', 'third trimester',
            'mahine ki hoon', 'weeks ki hoon', 'months along', 'ultrasound',
            '1st month', '2nd month', '3rd month', '4th month', '5th month',
            '6th month', '7th month', '8th month', '9th month',
            '1 month', '2 month', '3 month', '4 month', '5 month',
            '6 month', '7 month', '8 month', '9 month',
            'ek mahina', 'do mahine', 'teen mahine', 'char mahine',
        ],

        'goal_target' => [
            'lose 5', 'lose 10', 'lose 15', 'lose 20', 'lose 25', 'lose 30',
            'gain 5', 'gain 10', 'gain muscle', 'gain weight',
            'target weight', 'weight goal', 'kg lose', 'slim hona', 'fit hona',
            'want to lose', 'want to gain', 'target hai', 'goal hai',
            'kg chahiye', 'kg karna hai', 'mera target', 'meri goal',
            'i want to lose', 'i want to gain', 'my goal is', 'ideal weight', 'bmi',
            '5 kg', '6 kg', '7 kg', '8 kg', '9 kg', '10 kg', '12 kg',
            '15 kg', '20 kg', '25 kg', '30 kg', '3 kg', '4 kg',
            '5kg', '10kg', '15kg', '20kg', 'weight lose karna', 'weight kam karna',
            'no specific target', 'just fit', 'just healthy', 'no target',
            'koi target nahi', 'bas healthy',
        ],

        'diet' => [
            'breakfast mein', 'lunch mein', 'dinner mein', 'nashte mein',
            'subah mein khata', 'subah mein khati',
            'roti khata', 'roti khati', 'chawal khata', 'chawal khati',
            'dal khata', 'dal khati', 'sabzi khata', 'sabzi khati',
            'paratha khata', 'paratha khati', 'dosa khata', 'dosa khati',
            'outside khata', 'outside khati', 'ghar ka khana', 'bahar ka khana',
            'tiffin leta', 'tiffin leti', 'canteen mein', 'order karta', 'order karti',
            'veg hoon', 'non-veg hoon', 'vegetarian hoon', 'egg khata', 'egg khati',
            'milk pita', 'milk piti', 'chai pita', 'chai piti',
            'i usually eat', 'i eat roti', 'i eat rice', 'i have roti', 'i have rice',
            'i skip breakfast', 'i skip lunch', 'my diet is', 'calorie', 'junk food',
            'roti', 'rice', 'dal rice', 'roti sabzi', 'poha', 'upma', 'idli',
            'vegetarian', 'non vegetarian', 'veg', 'non-veg', 'ghar ka', 'homemade',
            'outside food', 'i eat', 'khata hoon', 'khati hoon', 'simple food', 'indian food',
        ],

        'activity' => [
            'walk karta', 'walk karti', 'gym jata', 'gym jaati', 'yoga karta', 'yoga karti',
            'exercise karta', 'exercise karti', 'zumba karta', 'zumba karti',
            'swimming karta', 'swimming karti', 'cycling karta', 'cycling karti',
            'koi exercise nahi', 'bilkul nahi chalta', 'sedentary hoon', 'mostly baithna',
            'desk job hai', 'work from home', 'ghar pe rehta', 'ghar pe rehti',
            'i walk daily', 'i go to gym', 'i do yoga', 'i exercise', 'no exercise',
            'dont exercise', 'not active', 'mostly sitting', 'i sit all day',
            'steps daily', 'active lifestyle', 'walk', 'walking', 'gym', 'yoga',
            'running', 'jogging', 'cycling', 'swimming', 'zumba', 'exercise', 'workout',
            'daily walk', 'morning walk', 'evening walk', '30 min', '45 min', '1 hour',
            'nahi karta', 'nahi karti', 'bahut kam', 'rarely', 'sedentary', 'inactive',
            'office job', 'sitting job', 'desk job', 'wfh',
        ],

        'sleep' => [
            'ghante sota', 'ghante soti', '6 ghante neend', '7 ghante neend',
            '8 ghante neend', '5 ghante neend', 'neend achi hai', 'neend nahi aati',
            'neend puri nahi', 'raat ko sota', 'raat ko soti', 'raat bhar jaagta',
            'late night tak', 'uthna mushkil', 'neend toot',
            'i sleep 5', 'i sleep 6', 'i sleep 7', 'i sleep 8',
            'hours of sleep', 'bad sleep', 'good sleep', 'poor sleep',
            'cant sleep', 'cannot sleep', 'dont sleep well', 'insomnia',
            'broken sleep', 'wake up at night', 'sleep late', 'sleep early',
            '4 hours', '5 hours', '6 hours', '7 hours', '8 hours', '9 hours',
            '4-5 hours', '5-6 hours', '6-7 hours', '7-8 hours',
            '4 ghante', '5 ghante', '6 ghante', '7 ghante', '8 ghante',
            'around 6', 'around 7', 'around 8', 'about 6', 'about 7', 'about 8',
            'sleep well', 'sleep okay', 'sleep fine', 'sleep bad',
            'theek neend', 'acchi neend', 'kam neend', 'late sona', 'jaldi sona',
        ],

        'stress' => [
            'bahut stress hai', 'kafi stress hai', 'stress nahi hai',
            'office ka stress', 'ghar ki tension', 'family tension',
            'work pressure', 'anxiety hai', 'anxious hoon',
            'overthink karta', 'overthink karti', 'bohot sochta',
            'irritable rehta', 'irritable rehti', 'mood swings', 'gussa aata',
            'depressed feel', 'low feel', 'demotivated',
            'i feel very stressed', 'i am stressed', 'not stressed at all',
            'very stressed', 'work stress', 'family stress', 'mental pressure',
            'quite relaxed', 'no stress', 'stress free', 'i am anxious',
            'i overthink', 'i worry a lot', 'i feel anxious', 'panic',
            'burnout', 'exhausted mentally', 'mentally tired',
            'stress hai', 'tension hai', 'stress nahi', 'tension nahi',
            'thoda stress', 'bahut stress', 'stressed', 'relaxed', 'calm', 'anxious',
            'stress rehta', 'stress rehti', 'tension rehti', 'office stress', 'home stress',
        ],

        // Additional fields for a complete clinical picture
        'water_intake' => [
            'paani pita', 'paani piti', 'water pita', 'water piti',
            'glass paani', 'litre paani', 'litre water', 'glass water',
            'i drink water', 'i drink 2', 'i drink 3', 'i drink 4',
            '2 litre', '3 litre', '1.5 litre', '8 glasses', '10 glasses',
            'paani kam pita', 'paani kam piti', 'barely drink water',
            'dehydrated', 'thoda paani', 'zyada paani',
        ],

        'medical_history' => [
            'blood pressure', 'bp high', 'bp low', 'hypertension', 'cholesterol',
            'heart problem', 'thyroid bhi hai', 'pcod bhi', 'diabetes bhi',
            'kidney problem', 'liver problem', 'surgery hui', 'operation hua',
            'no other condition', 'sirf yahi hai', 'bas yahi problem',
            'koi aur bimari nahi', 'healthy otherwise', 'otherwise healthy',
            'allergy hai', 'allergic hoon', 'no allergy', 'koi allergy nahi',
        ],
    ];

    public function __construct(
        private LLMRouter              $llm,
        private MemoryExtractorService $memoryExtractor,
        private LanguageDetector       $languageDetector,
        private MoodAnalyzer           $moodAnalyzer,
        private SentimentAnalyzer      $sentimentAnalyzer,
    ) {}

    // =========================================================================
    // STATIC GREETING MESSAGES
    // =========================================================================

    public function getWelcomeMessage(User $user, string $lang = 'en'): string
    {
        $name = $user->first_name ?? '';
        $user->loadMissing(['goals']);
        $goal = strtolower($user->goals->pluck('name')->first() ?? '');
        $hi   = $name ? "Hey {$name}! 🌸" : "Hey! 🌸";

        return $this->isHindi($lang)
            ? "{$hi} Main Rakhi hoon — aapki personal health coach. 😊\n\n"
              . "{$this->goalAcknowledgement($goal, $lang)}\n\n"
              . "{$this->openingQuestion($goal, $lang)}"
            : "{$hi} I'm Rakhi — your personal health coach. 😊\n\n"
              . "{$this->goalAcknowledgement($goal, $lang)}\n\n"
              . "{$this->openingQuestion($goal, $lang)}";
    }

    public function getCallInviteMessage(string $lang = 'en'): string
    {
        return $this->isHindi($lang)
            ? "Agar seedha baat karni ho toh upar Call Icon tap karein — main wahan bhi hoon 😊"
            : "If you'd like to talk directly, tap the Call Icon at the top — I'm there too 😊";
    }

    public function getReturningUserGreeting(User $user, string $lang = 'en', bool $hasActiveSession = false): string
    {
        if ($hasActiveSession) return '';

        $name = $user->first_name ?? '';
        $user->loadMissing(['goals']);
        $goal = strtolower($user->goals->pluck('name')->first() ?? '');
        $hi   = $name ? "Hey {$name}! 👋" : "Hey! 👋";

        return $this->isHindi($lang)
            ? "{$hi} Wapas aaye! {$this->returningContext($goal, $lang)}"
            : "{$hi} Good to have you back! {$this->returningContext($goal, $lang)}";
    }

    public function getVoiceWelcomeMessage(User $user, string $lang = 'en'): string
    {
        $name = $user->first_name ?? '';
        $user->loadMissing(['goals']);
        $goal   = strtolower($user->goals->pluck('name')->first() ?? '');
        $warmth = $this->voiceGoalWarmth($goal, $lang);

        return $this->isHindi($lang)
            ? ($name
                ? "Hey {$name}, main Rakhi bol rahi hoon. 😊 {$warmth} Kaise feel kar rahe hain aajkal?"
                : "Hey, main Rakhi bol rahi hoon. 😊 {$warmth} Kaise feel kar rahe hain aajkal?")
            : ($name
                ? "Hey {$name}, this is Rakhi. 😊 {$warmth} How have you been feeling lately?"
                : "Hey, this is Rakhi. 😊 {$warmth} How have you been feeling lately?");
    }

    public function getChatOpener(User $user, string $lang = 'en'): string
    {
        $name = $user->first_name ?? '';
        $user->loadMissing(['goals']);
        $goal = strtolower($user->goals->pluck('name')->first() ?? '');
        $hi   = $name ? "Hey {$name}! 🌸" : "Hey! 🌸";

        return $this->isHindi($lang)
            ? "{$hi} {$this->goalAcknowledgement($goal, $lang)}\n\nChalo chat se shuru karte hain — {$this->openingQuestion($goal, $lang)}"
            : "{$hi} {$this->goalAcknowledgement($goal, $lang)}\n\nLet's start over chat — {$this->openingQuestion($goal, $lang)}";
    }

    public function getCompletionMessage(string $firstName = '', string $lang = 'en'): string
    {
        $n = $firstName ? ", {$firstName}" : '';
        return $this->isHindi($lang)
            ? "Bohot accha{$n} — aapne jo share kiya, ussi ke hisaab se main abhi aapka plan bana rahi hoon. Thoda sa wait karein 😊"
            : "That's really helpful{$n} — I'm putting together your personalized plan right now based on everything you've shared. Just a moment 😊";
    }

    // =========================================================================
    // MAIN CONSULTATION RESPONSE
    // =========================================================================

    public function getConsultationResponse(
        ChatSession $session,
        User        $user,
        string      $userMessage,
        bool        $voice = false
    ): string {
        $user->loadMissing(['goals']);

        $name = $user->first_name ?? '';
        $goal = strtolower($user->goals->pluck('name')->first() ?? 'general');

        // Language is set once and never flipped back mid-conversation
        $detectedLang = $this->languageDetector->detect($userMessage);
        $lang = ($detectedLang !== 'en') ? $detectedLang : ($session->detected_language ?? 'en');

        if ($lang !== $session->detected_language) {
            $session->update(['detected_language' => $lang]);
        }

        $mood           = $this->moodAnalyzer->analyze($userMessage);
        $sentimentScore = $this->sentimentAnalyzer->score($userMessage);
        $sentiment      = $this->sentimentAnalyzer->analyze($userMessage);
        $distressed     = $sentimentScore <= self::EMOTIONAL_THRESHOLD;

        $fullHistory = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'message' => $m->message])
            ->values()
            ->toArray();

        $windowedHistory = array_slice($fullHistory, max(0, count($fullHistory) - self::HISTORY_WINDOW));

        if ($this->isVague($userMessage)) {
            return $this->vagueResponse($lang, $name, $mood);
        }

        try {
            $this->memoryExtractor->extractAndStore($user, $userMessage);
        } catch (\Throwable $e) {
            Log::warning('MemoryExtractor skipped: ' . $e->getMessage());
        }

        $collectedFields = $this->getCollectedFields($fullHistory, $goal);
        $missingFields   = $this->getMissingFieldsFromFlow($goal, $collectedFields);
        $nextField       = $missingFields[0] ?? null;

        $lastAI    = collect($fullHistory)->where('role', 'rakhi')->last()['message'] ?? '';
        $userTurns = collect($fullHistory)->where('role', 'user')->count();
        $ready     = ($userTurns >= self::MIN_USER_TURNS) && empty($missingFields);

        $prompt = $this->buildPrompt(
            name            : $name,
            goal            : $goal,
            lang            : $lang,
            mood            : $mood,
            sentiment       : $sentiment,
            sentimentScore  : $sentimentScore,
            distressed      : $distressed,
            collectedFields : $collectedFields,
            missingFields   : $missingFields,
            nextField       : $nextField,
            lastAI          : $lastAI,
            ready           : $ready,
            voice           : $voice,
            userMessage     : $userMessage,
            answersSummary  : $this->buildUserAnswersSummary($fullHistory),
        );

        return $this->llm->chat($prompt, $windowedHistory);
    }

    // =========================================================================
    // PLAN GENERATION
    // =========================================================================

    public function shouldGeneratePlans(ChatSession $session): bool
    {
        $user    = $session->user()->with('goals')->first();
        $goal    = strtolower($user?->goals->pluck('name')->first() ?? 'general');
        $history = ChatMessage::where('session_id', $session->id)
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'message' => $m->message])
            ->toArray();

        $turns     = collect($history)->where('role', 'user')->count();
        $collected = $this->getCollectedFields($history, $goal);
        $missing   = $this->getMissingFieldsFromFlow($goal, $collected);

        return $turns >= self::MIN_USER_TURNS && empty($missing);
    }

    public function generateAllPlans(User $user, int $sessionId): void
    {
        try {
            $conversation = ChatMessage::where('session_id', $sessionId)
                ->orderBy('id')
                ->get()
                ->map(fn($m) => ucfirst($m->role) . ': ' . $m->message)
                ->join("\n");
            $this->memoryExtractor->extractFromConversation($user, $conversation);
        } catch (\Throwable $e) {
            Log::warning('Bulk memory extraction skipped: ' . $e->getMessage());
        }

        $user->update(['first_consultation_complete' => true]);

        dispatch(new GenerateConsultationReport($user, $sessionId));
        dispatch(new GenerateDietPlan($user, $sessionId));
        dispatch(new GenerateFitnessPlan($user, $sessionId));
    }

    // =========================================================================
    // FIELD DETECTION — negation-aware, user messages only
    // =========================================================================

    private function getCollectedFields(array $history, string $goal): array
    {
        $userText = strtolower(implode(' ', array_column(
            array_filter($history, fn($m) => ($m['role'] ?? '') === 'user'),
            'message'
        )));

        $collected = [];

        foreach ($this->getGoalFlow($goal) as $field) {
            $signals = self::FIELD_SIGNALS[$field] ?? [];

            foreach ($signals as $signal) {
                $pos = strpos($userText, $signal);
                if ($pos === false) continue;

                $window  = substr($userText, max(0, $pos - 40), 40);
                $negated = false;

                foreach (self::NEGATION_PREFIXES as $neg) {
                    if (str_contains($window, $neg)) {
                        $negated = true;
                        break;
                    }
                }

                // "No exercise" / "no stress" / "bad sleep" are still valid answers
                if ($negated && !in_array($field, self::SELF_NEGATING_FIELDS, true)) {
                    continue;
                }

                $collected[] = $field;
                break;
            }
        }

        return array_unique($collected);
    }

    private function getMissingFieldsFromFlow(string $goal, array $collected): array
    {
        return array_values(array_diff($this->getGoalFlow($goal), $collected));
    }

    // =========================================================================
    // GOAL FLOWS — what to collect, in order
    // =========================================================================

    private function getGoalFlow(string $goal): array
    {
        return match (true) {
            str_contains($goal, 'diabet')  => ['condition_detail', 'medical_history', 'diet', 'activity', 'sleep', 'stress', 'water_intake'],
            str_contains($goal, 'pcos')    => ['cycle', 'medical_history', 'diet', 'activity', 'sleep', 'stress', 'water_intake'],
            str_contains($goal, 'thyroid') => ['medication', 'medical_history', 'diet', 'activity', 'sleep', 'stress', 'water_intake'],
            str_contains($goal, 'pregnan') => ['stage', 'medical_history', 'diet', 'activity', 'sleep', 'stress', 'water_intake'],
            str_contains($goal, 'weight')  => ['goal_target', 'medical_history', 'diet', 'activity', 'sleep', 'stress', 'water_intake'],
            default                        => ['medical_history', 'diet', 'activity', 'sleep', 'stress', 'water_intake'],
        };
    }

    // =========================================================================
    // PROMPT BUILDER
    // =========================================================================

    private function buildPrompt(
        string  $name,
        string  $goal,
        string  $lang,
        string  $mood,
        string  $sentiment,
        float   $sentimentScore,
        bool    $distressed,
        array   $collectedFields,
        array   $missingFields,
        ?string $nextField,
        string  $lastAI,
        bool    $ready,
        bool    $voice,
        string  $userMessage,
        string  $answersSummary = '',
    ): string {

        $langInstr    = $this->languageInstruction($lang);
        $collectedStr = empty($collectedFields) ? 'None yet.' : implode(', ', $collectedFields);
        $missingStr   = empty($missingFields)   ? 'All done.' : implode(', ', $missingFields);
        $nextInstr    = $nextField ? $this->nextFieldInstruction($nextField, $goal) : '';

        $answersBlock = $answersSummary
            ? "\nWHAT THE USER HAS ALREADY TOLD YOU — do NOT ask about these again:\n{$answersSummary}"
            : '';

        $antiRepeat = $lastAI
            ? "ANTI-REPETITION: Your last message was:\n\"{$lastAI}\"\nDo NOT repeat or rephrase it. Start from a completely fresh angle."
            : '';

        $emotionalMode = $distressed
            ? "EMOTIONAL PRIORITY: User seems stressed or upset. First acknowledge their feelings warmly (1 sentence). Then gently guide back to the next question. Never rush past emotions to collect data."
            : '';

        $planInstr = $ready
            ? "ALL FIELDS COLLECTED — WRAP UP:\n"
              . "1. Write 2 warm sentences reflecting what you've learned — be specific about their condition, lifestyle, and goal. Make them feel truly understood.\n"
              . "2. Tell them their plan is being prepared:\n"
              . "   English:  \"Give me just a moment… I'm putting together your personalized plan right now 😊\"\n"
              . "   Hinglish: \"Bohot accha — main abhi aapke liye plan bana rahi hoon, thoda sa wait karein 😊\"\n"
              . "3. On the very next line output ONLY: [GENERATE_PLANS]\n"
              . "   Nothing after it. No emoji, no text."
            : "CONTINUE CONSULTATION:\n"
              . "Fields still needed: {$missingStr}\n"
              . "Ask about: {$nextField}\n"
              . $nextInstr;

        $lengthInstr = $voice
            ? "LENGTH: 1 warm, natural sentence. Voice-friendly — no lists."
            : "LENGTH: Max 3 short sentences. Mobile-friendly. No bullet points, no walls of text.";

        return <<<PROMPT
You are Rakhi — a warm, empathetic Indian health coach conducting a first consultation.
Your job is to gather all the health information needed to create a personalized diet plan, fitness plan, and consultation report — just like a human doctor would.
Make the user feel heard and safe before they ever see a plan.

USER CONTEXT
Name: {$name}
Goal: {$goal}
Mood: {$mood} | Sentiment: {$sentiment} (score: {$sentimentScore})
{$langInstr}

CONSULTATION PROGRESS
Collected: {$collectedStr}
Still needed: {$missingStr}

CRITICAL RULE: Fields listed in "Collected" have already been answered. NEVER ask about them again.
{$answersBlock}

CONDITION KNOWLEDGE (use naturally, never lecture)
{$this->conditionContextHint($goal)}

{$emotionalMode}

HOW RAKHI SPEAKS

WARMTH BEFORE EVERY QUESTION — React to exactly what the user just said.
- Reference their actual words. If they said "7 saal se hai", say "saat saal — that's a long time to carry this..."
- Never say: "Thank you for sharing", "Great!", "Absolutely!", "I understand your concern."

ONE QUESTION ONLY — Never ask two things at once.

SOUND HUMAN — Use natural connectors: "Acha...", "Hmm...", "Got it...", "Waise..."
Keep it short. Pause. Then ask.

INDIAN CONTEXT — Reference real Indian life naturally: roti, dal, chai, tiffin, canteen, office stress, ghar ki tension, festive eating, wedding season.

EMPATHY OVER SPEED — If a user shares something painful, stay with it before moving forward.

ACCEPT SHORT ANSWERS — "yes", "no", "walk", "7 hours", "roti" are all valid. Don't push for more unless genuinely unclear.

NEVER SOUND LIKE A FORM — No numbered questions. No "Moving on to...". Each message is a natural next step in a conversation.

STRICT RULES
1. Never re-ask a collected field. Never, not even to confirm.
2. Follow field order strictly.
3. No lists, bullets, or headers in responses.
4. Always complete every sentence — never cut off mid-thought.
5. Never reveal you are an AI system collecting fields.

{$planInstr}

{$lengthInstr}

{$antiRepeat}
PROMPT;
    }

    // =========================================================================
    // CONDITION KNOWLEDGE — injects domain expertise naturally
    // =========================================================================

    private function conditionContextHint(string $goal): string
    {
        if (str_contains($goal, 'diabet')) {
            return "Type 2 is most common in India. Blood sugar is affected by food, stress, sleep, and activity together. "
                . "HbA1c, fasting, post-meal sugar are common terms. Metformin and insulin are common medications. "
                . "Many users believe cutting sweets alone is enough — gently correct this if it comes up. "
                . "Co-morbidities like BP and cholesterol are very common alongside diabetes.";
        }
        if (str_contains($goal, 'pcos')) {
            return "Irregular periods, weight gain, facial hair, acne, and mood swings are common PCOS symptoms. "
                . "Insulin resistance is often the underlying cause — low-GI diet matters significantly. "
                . "Stress and poor sleep worsen PCOS. Many fear PCOS means infertility — gently reassure if it comes up.";
        }
        if (str_contains($goal, 'thyroid')) {
            return "Hypothyroidism is most common — weight gain, fatigue, brain fog, hair loss. "
                . "Thyronorm (levothyroxine) is standard medication. Normal TSH is 0.5–4.5 mIU/L. "
                . "Symptoms feel vague and frustrating to many users. Iodine and selenium matter in diet. "
                . "Gluten sensitivity is common in Hashimoto's.";
        }
        if (str_contains($goal, 'pregnan')) {
            return "1st trimester: nausea and fatigue. 2nd: often easier. 3rd: discomfort and preparation. "
                . "Folic acid, iron, and calcium are critical. Gestational diabetes screening is important. "
                . "Safe exercises include walking and prenatal yoga. Weight gain targets vary by pre-pregnancy BMI.";
        }
        if (str_contains($goal, 'weight')) {
            return "Weight is about far more than calories — hormones, sleep, stress, and gut health all play a role. "
                . "Crash diets cause muscle loss and rebound. Sustainable loss is 0.5–1 kg/week. "
                . "Emotional and stress eating are very common in Indian households. "
                . "Many users have tried and failed multiple plans — acknowledge that frustration.";
        }
        return "Energy, sleep, digestion, hydration, and stress are all deeply connected. "
            . "A holistic view of daily lifestyle matters more than any single fix.";
    }

    // =========================================================================
    // NEXT-FIELD INSTRUCTIONS — how to ask, what to accept
    // =========================================================================

    private function nextFieldInstruction(?string $field, string $goal): string
    {
        if ($field === null) return '';

        return match ($field) {

            'condition_detail' =>
                "Ask which type of diabetes — Type 1, Type 2, gestational, or prediabetes — and how long they've had it. "
                . "If they only said 'sugar hai' or 'diabetes hai' without specifying type, gently clarify. "
                . "Do NOT ask about diet or medication yet. "
                . "Feel: \"Seven years — that's a long time to manage this. Is it Type 2? And were you put on any medication for it?\"",

            'cycle' =>
                "Ask whether their cycle is regular or irregular, and roughly how many days. "
                . "If they mentioned period issues already, ask specifically what's been off. "
                . "Feel: \"How has your cycle been — pretty regular or does it skip sometimes?\"",

            'medication' =>
                "Ask whether they're currently on thyroid medication and if they know their last TSH level. "
                . "If they don't know their TSH, that's fine — just note whether they take meds. "
                . "Feel: \"Are you on any thyroid medication right now? And do you know your last TSH level?\"",

            'stage' =>
                "Ask which month or trimester they're in. Be especially warm — pregnancy is personal. "
                . "Feel: \"How far along are you — which month or trimester?\"",

            'goal_target' =>
                "Ask how much weight they want to lose or gain, and if they have a timeline in mind. "
                . "If they gave a vague goal already, ask for a specific number. "
                . "Feel: \"Do you have a number in mind — how many kilos are you aiming for?\"",

            'medical_history' =>
                "Ask if there are any other health conditions, medications, surgeries, or allergies they should mention. "
                . "Keep it conversational — not like a form. "
                . "Feel: \"Is there anything else health-wise I should know about — any other conditions, medications, or allergies?\"",

            'diet' =>
                "Ask what a full typical day of eating looks like — morning to night. "
                . "Reference Indian food naturally: roti, rice, dal, tiffin, ghar ka khana, bahar ka khana. "
                . "If they mentioned one meal already, ask about the rest of the day. "
                . "Feel: \"What does a typical day of eating look like for you — from morning to night? Ghar ka zyada hota hai ya bahar ka?\"",

            'activity' =>
                "Ask how much physical movement they get in a typical day. "
                . "If they say no exercise, accept it without judgment — ask if they at least walk. "
                . "Feel: \"How much movement do you get in a day — do you walk, go to the gym, or is it mostly sitting?\"",

            'sleep' =>
                "Ask how many hours they sleep and whether they wake up feeling rested. "
                . "If they already seemed tired or exhausted, acknowledge that first. "
                . "Feel: \"How's your sleep been — roughly how many hours, and do you wake up feeling rested?\"",

            'stress' =>
                "Ask what stress levels have been like — work, family, or anything else weighing on them. "
                . "If the user already seemed stressed throughout the conversation, acknowledge it warmly before asking. "
                . "Feel: \"How have stress levels been lately — is there anything that's been on your mind a lot?\"",

            'water_intake' =>
                "Ask roughly how much water they drink in a day — glasses or litres. "
                . "Keep it light and quick — this is the last question before the plan. "
                . "Feel: \"And one last thing — how much water do you usually drink in a day?\"",

            default => '',
        };
    }

    // =========================================================================
    // LANGUAGE HANDLING
    // =========================================================================

    private function isHindi(string $lang): bool
    {
        return str_starts_with($lang, 'hi') || $lang === 'hi-roman';
    }

    private function languageInstruction(string $lang): string
    {
        return match (true) {
            $this->isHindi($lang) =>
                "LANGUAGE: Respond entirely in warm Hinglish — Hindi words in English script. "
                . "Natural mix like a real Indian health coach would speak. "
                . "No Devanagari script. No formal Hindi. Stay consistent throughout the reply. "
                . "Example: 'Acha, saat saal — that's a long time. Koi medicine bhi chal rahi hai?'",

            $lang === 'ta' => "LANGUAGE: Respond entirely in warm, natural Tamil. No mixing with English or Hindi.",
            $lang === 'te' => "LANGUAGE: Respond entirely in warm, natural Telugu. No mixing with English or Hindi.",
            $lang === 'mr' => "LANGUAGE: Respond entirely in warm, natural Marathi. No mixing with English or Hindi.",
            $lang === 'bn' => "LANGUAGE: Respond entirely in warm, natural Bengali. No mixing with English or Hindi.",

            default =>
                "LANGUAGE: Respond entirely in clear, warm, conversational English. "
                . "Reference Indian food, lifestyle, and context naturally where it fits. "
                . "No Hindi phrases.",
        };
    }

    // =========================================================================
    // GOAL-SPECIFIC COPY — acknowledgements, openers, warmth
    // =========================================================================

    private function goalAcknowledgement(string $goal, string $lang): string
    {
        $hi = $this->isHindi($lang);

        return match (true) {
            str_contains($goal, 'diabet') => $hi
                ? "Diabetes manage karna ek daily challenge hai — food, timing, stress, neend, sab kuch blood sugar ko affect karta hai. Aapne yeh step liya, main samajhti hoon yeh kitna important hai. 💛"
                : "Managing diabetes is a daily challenge — food, timing, stress, sleep, it all affects blood sugar. I'm really glad you took this step, and I'm here to make it easier. 💛",

            str_contains($goal, 'pcos') => $hi
                ? "PCOS sirf ek medical term nahi — yeh body, mood, energy, cycles — sab ek saath affect karta hai. Main samajhti hoon yeh kitna overwhelming ho sakta hai. 💛"
                : "PCOS isn't just one thing — it touches your weight, your mood, your cycles, your energy. I know it can feel overwhelming, and I'm here to help you understand your own body better. 💛",

            str_contains($goal, 'thyroid') => $hi
                ? "Thyroid ki problem bahut tricky hoti hai — fatigue, weight, mood, hair — sab kuch. Aur sabse frustrating yeh hai ki log aksar samajhte nahi. Main samajhti hoon. 💛"
                : "Thyroid issues are tricky — tiredness, weight changes, mood, hair loss. And it's frustrating when people don't take it seriously. I do. 💛",

            str_contains($goal, 'weight') => $hi
                ? "Weight ke saath struggle sirf khaana-peena nahi hota — hormones, stress, neend, lifestyle sab connected hai. Har kisi ki story alag hoti hai. Mujhe aapki story samajhni hai. 💛"
                : "Struggling with weight is about so much more than food — hormones, stress, sleep, lifestyle all play a role. Everyone's story is different. I want to understand yours. 💛",

            str_contains($goal, 'pregnan') => $hi
                ? "Pregnancy ke dauran apna khayal rakhna — yeh aapke aur baby dono ke liye bahut important hai. Har trimester alag hota hai, aur main aapke saath hoon har step pe. 💛"
                : "Taking care of yourself through pregnancy is one of the most important things you can do — for you and your baby. I'm here for every step. 💛",

            default => $hi
                ? "Apni health ka khayal rakhna — yeh bahut important decision hai. Main aapke saath hoon is journey mein. 💛"
                : "Taking care of your health is one of the most important decisions you can make. I'm really glad you're here. 💛",
        };
    }

    private function openingQuestion(string $goal, string $lang): string
    {
        $hi = $this->isHindi($lang);

        return match (true) {
            str_contains($goal, 'diabet') => $hi
                ? "Pehle thoda aapke baare mein samajhna chahti hoon — kitne time se chal raha hai aapka diabetes, aur aajkal kaisa lag raha hai overall?"
                : "Before we dive in — how long have you been managing your diabetes, and how have things been going lately?",

            str_contains($goal, 'pcos') => $hi
                ? "Thoda batayein — PCOS ka pata kab chala, aur abhi sabse zyada kya cheez bother kar rahi hai?"
                : "Tell me — when did you find out about PCOS, and what's been bothering you the most lately?",

            str_contains($goal, 'thyroid') => $hi
                ? "Thoda batayein — thyroid ka pata kab chala, aur abhi kaisa feel ho raha hai body mein?"
                : "Tell me — when did you find out about your thyroid, and how has your body been feeling lately?",

            str_contains($goal, 'weight') => $hi
                ? "Batayein — weight ke saath struggle kab se chal raha hai, aur pehle kuch try kiya hai ya pehli baar ho?"
                : "Tell me — how long has weight been a challenge, and have you tried anything before or is this your first time?",

            str_contains($goal, 'pregnan') => $hi
                ? "Pehle congratulations! 🌸 Batayein — abhi kitne mahine chal rahe hain, aur kaisa feel ho raha hai?"
                : "First of all, congratulations! 🌸 Tell me — how far along are you, and how have you been feeling?",

            default => $hi
                ? "Pehle thoda aapke baare mein samajhna chahti hoon — batayein, aajkal kya chal raha hai? 😊"
                : "I'd love to understand you a little better — tell me, what's been going on lately? 😊",
        };
    }

    private function voiceGoalWarmth(string $goal, string $lang): string
    {
        $hi = $this->isHindi($lang);

        return match (true) {
            str_contains($goal, 'diabet')  => $hi ? "Diabetes ke saath aapki journey samajhna chahti hoon." : "I'd love to understand your journey with diabetes.",
            str_contains($goal, 'pcos')    => $hi ? "PCOS ke baare mein baat karenge aaram se." : "We'll talk through your PCOS together.",
            str_contains($goal, 'thyroid') => $hi ? "Thyroid ke baare mein poori baat karenge." : "We'll go through your thyroid situation together.",
            str_contains($goal, 'pregnan') => $hi ? "Aur congratulations! Pregnancy ke baare mein baat karte hain." : "And congratulations! Let's talk through your pregnancy.",
            str_contains($goal, 'weight')  => $hi ? "Weight journey ke baare mein samajhna chahti hoon." : "I want to understand your weight journey.",
            default                        => $hi ? "Aapki health journey samajhna chahti hoon." : "I want to understand your health journey.",
        };
    }

    private function returningContext(string $goal, string $lang): string
    {
        $hi = $this->isHindi($lang);

        return match (true) {
            str_contains($goal, 'diabet')  => $hi ? "Blood sugar kaisa chal raha hai aajkal? 😊" : "How has your blood sugar been lately? 😊",
            str_contains($goal, 'weight')  => $hi ? "Journey kaisi chal rahi hai? 😊" : "How has the journey been going? 😊",
            str_contains($goal, 'pcos'),
            str_contains($goal, 'thyroid') => $hi ? "Body kaisi feel ho rahi hai aajkal? 😊" : "How has your body been feeling? 😊",
            str_contains($goal, 'pregnan') => $hi ? "Aap aur baby — dono theek hain? 😊" : "How are you and the baby doing? 😊",
            default                        => $hi ? "Aaj kaisa feel ho raha hai? 😊" : "How are you doing today? 😊",
        };
    }

    // =========================================================================
    // USER ANSWERS SUMMARY — prevents the LLM from re-asking answered fields
    // =========================================================================

    private function buildUserAnswersSummary(array $history): string
    {
        $userMessages = array_filter($history, fn($m) => ($m['role'] ?? '') === 'user');
        if (empty($userMessages)) return '';

        $lines = [];
        foreach (array_values($userMessages) as $i => $msg) {
            $lines[] = 'Turn ' . ($i + 1) . ': ' . substr(trim($msg['message']), 0, 200);
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // VAGUE INPUT
    // =========================================================================

    private function isVague(string $msg): bool
    {
        return strlen(trim(strip_tags($msg))) <= 1;
    }

    private function vagueResponse(string $lang, string $name, string $mood = ''): string
    {
        $n = $name ? ", {$name}" : '';

        if (in_array(strtolower($mood), ['sad', 'frustrated', 'anxious', 'upset'])) {
            return $this->isHindi($lang)
                ? "Koi baat nahi{$n} — jo feel ho raha hai wahi share karein, main sun rahi hoon 😊"
                : "No worries{$n} — whatever you're feeling, I'm here to listen 😊";
        }

        return $this->isHindi($lang)
            ? "Koi baat nahi{$n} — jo comfortable lage wahi batayein, koi rush nahi 😊"
            : "No worries{$n} — share whatever feels comfortable, there's no rush 😊";
    }

    // =========================================================================
    // PUBLIC API — used by ChatController
    // =========================================================================

    public function getMissingFields(array $history, string $goal = 'general'): array
    {
        return $this->getMissingFieldsFromFlow($goal, $this->getCollectedFields($history, $goal));
    }

    public function hasEnoughContext(array $history, int $userTurns, string $goal = 'general'): bool
    {
        return $userTurns >= self::MIN_USER_TURNS && empty($this->getMissingFields($history, $goal));
    }
}
