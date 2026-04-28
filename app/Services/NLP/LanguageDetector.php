<?php

namespace App\Services\NLP;

class LanguageDetector
{
    // ── Script detection character sets ──────────────────────────────────────

    private array $devanagariChars = [
        'क', 'ख', 'ग', 'घ', 'ह', 'ा', 'ि', 'ी', 'ु', 'ू',
        'े', 'ै', 'ो', 'ौ', 'म', 'न', 'र', 'ल', 'व', 'स',
        'अ', 'आ', 'इ', 'ई', 'उ', 'ऊ', 'ए', 'ओ', 'ं', 'ः',
    ];

    private array $tamilChars  = ['அ', 'ஆ', 'இ', 'ஈ', 'உ', 'ஊ', 'எ', 'ஏ', 'ை', 'ொ', 'ோ', 'க', 'ச', 'ட', 'த', 'ப', 'ம'];
    private array $teluguChars = ['అ', 'ఆ', 'ఇ', 'ఈ', 'ఉ', 'ఊ', 'క', 'గ', 'చ', 'జ', 'ట', 'డ', 'త', 'ద', 'న', 'ప'];
    private array $marathiChars = ['अ', 'आ', 'इ', 'ई', 'उ', 'ऊ', 'क', 'ग', 'च', 'ज', 'ट', 'ड', 'त', 'द', 'न', 'प', 'ळ', 'ण'];

    // ── Hinglish (Roman Hindi) word list ─────────────────────────────────────
    // Ordered by specificity — more unique Hindi words first

    private array $hinglishWords = [
        // Core Hindi words that never appear in English
        'kya', 'hai', 'hain', 'mera', 'meri', 'mujhe', 'haan', 'nahi', 'nahin',
        'bahut', 'thoda', 'zyada', 'abhi', 'kal', 'aaj', 'subah', 'raat', 'dopahar',
        'khana', 'khaana', 'pani', 'paani', 'dard', 'thakan', 'neend', 'dawai',
        'bata', 'batao', 'karo', 'karna', 'chahiye', 'lagta', 'lagti', 'samajh',
        'mein', 'tum', 'aap', 'woh', 'yeh', 'ye', 'bolo', 'bolna', 'sunna',
        'zaroor', 'bilkul', 'theek', 'thik', 'accha', 'acha', 'achha',
        'pareshan', 'takleef', 'kamzori', 'chakkar', 'bukhaar', 'ulti',
        'khush', 'udaas', 'thaka', 'thak', 'ghabra', 'tension',
        'roti', 'dal', 'sabzi', 'chawal', 'dahi', 'chai',
        'vyayam', 'kasrat', 'chalna', 'daudna',
        'isliye', 'kyunki', 'lekin', 'aur', 'toh', 'phir',
        'kitna', 'kitni', 'kaisa', 'kaisi', 'kaise', 'kab', 'kahan',
        'ho raha', 'ho rahi', 'kar raha', 'kar rahi', 'le raha', 'le rahi',
        'nahi ho', 'nahi kar', 'nahi tha', 'nahi thi',
        'se pehle', 'ke baad', 'ke liye', 'ke saath',
        'main hoon', 'aap hain', 'woh hai',
        'kal se', 'aaj se', 'abhi se', 'subah se', 'raat se',
        'bahut zyada', 'thoda sa', 'bilkul nahi',
    ];

    // ── Explicit language switch phrases ─────────────────────────────────────

    private array $languageRequests = [
        'hindi'    => ['hindi mein', 'hindi me', 'in hindi', 'hindi bolo', 'hindi mai', 'hindi main', 'speak hindi', 'talk hindi', 'reply hindi', 'hindi mein baat'],
        'hinglish' => ['hinglish', 'roman hindi', 'english mein hindi', 'roman mein'],
        'tamil'    => ['tamil mein', 'in tamil', 'tamil la', 'tamil bolo', 'tamil lo'],
        'telugu'   => ['telugu lo', 'in telugu', 'telugu mein', 'telugu lo baat'],
        'english'  => ['in english', 'english mein', 'english me', 'speak english', 'english bolo'],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // PRIMARY DETECTION — returns ['lang' => ..., 'script' => ...]
    // lang:   'en' | 'hi' | 'hi-roman' | 'ta' | 'te' | 'mr'
    // script: 'latin' | 'devanagari' | 'roman' | 'tamil' | 'telugu' | 'marathi'
    // ─────────────────────────────────────────────────────────────────────────

    public function detectFull(string $message): array
    {
        $lower = strtolower($message);

        // 1. Explicit language switch request
        foreach ($this->languageRequests as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    return $this->makeResult($lang . '-request', 'roman');
                }
            }
        }

        // 2. Script detection — count chars per script to handle mixed messages
        $devanagariCount = $this->countScriptChars($message, $this->devanagariChars);
        $marathiCount    = $this->countScriptChars($message, $this->marathiChars);
        $tamilCount      = $this->countScriptChars($message, $this->tamilChars);
        $teluguCount     = $this->countScriptChars($message, $this->teluguChars);

        // Marathi-specific chars (ळ, ण) distinguish from Hindi Devanagari
        $marathiSpecific = $this->countScriptChars($message, ['ळ', 'ण', 'ज्ञ']);

        if ($tamilCount > 0)  return $this->makeResult('ta', 'tamil');
        if ($teluguCount > 0) return $this->makeResult('te', 'telugu');

        if ($devanagariCount > 0) {
            // Even if user types in Devanagari, always respond in Roman Hindi (Hinglish)
            // Devanagari is never used in responses — mirror back as hi-roman
            return $this->makeResult('hi-roman', 'roman');
        }

        // 3. Roman Hindi (Hinglish) detection — word matching
        $hinglishScore = $this->scoreHinglish($lower);
        if ($hinglishScore >= 1) {
            return $this->makeResult('hi-roman', 'roman');
        }

        // 4. Default: English
        return $this->makeResult('en', 'latin');
    }

    /**
     * Legacy single-value detect — returns lang code string.
     * Kept for backward compatibility with existing callers.
     */
    public function detect(string $message): string
    {
        return $this->detectFull($message)['lang'];
    }

    /**
     * Detect only the script used in a message.
     * Returns: 'roman' | 'devanagari' | 'tamil' | 'telugu' | 'marathi' | 'latin'
     */
    public function detectScript(string $message): string
    {
        return $this->detectFull($message)['script'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LANGUAGE INSTRUCTION BUILDER
    // Takes both lang and script for precise mirroring
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build instruction using both language and script.
     * This is the preferred method — use this instead of getLanguageInstruction().
     */
    public function buildInstruction(string $lang, string $script): string
    {
        // Explicit switch requests
        if (str_ends_with($lang, '-request')) {
            return $this->getSwitchInstruction($lang, $script);
        }

        return match(true) {
            $lang === 'hi' || $lang === 'hi-roman' => $this->romanHindiInstruction(),
            $lang === 'ta'                         => $this->tamilInstruction(),
            $lang === 'te'                         => $this->teluguInstruction(),
            $lang === 'mr'                         => $this->marathiInstruction(),
            default                                => $this->englishInstruction(),
        };
    }

    /**
     * Legacy method — kept for backward compatibility.
     * Internally calls buildInstruction with inferred script.
     */
    public function getLanguageInstruction(string $langCode): string
    {
        return $this->buildInstruction($langCode, 'roman');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INSTRUCTION BUILDERS — one per script/language combination
    // ─────────────────────────────────────────────────────────────────────────

    private function romanHindiInstruction(): string
    {
        return <<<'INST'
LANGUAGE RULE (CRITICAL): The user is writing in Roman Hindi (Hinglish). You MUST reply in Roman Hindi — Hindi words written in English/Roman letters.

NEVER use Devanagari script (no Hindi Unicode characters like क, ख, ग, etc.).
NEVER switch to pure English mid-response.
NEVER mix languages randomly — stay consistent throughout the entire reply.
NEVER announce the language you are using.

Consistency examples:
- User: "mujhe bahut thakan ho rahi hai" → Reply: "Yeh thakan usually blood sugar ke fluctuation se hoti hai. Lunch mein thoda protein shamil karein — paneer ya dal bhi kaam karega. Har meal ke baad hota hai ya kuch specific meals ke baad?"
- User: "kya main exercise kar sakti hoon" → Reply: "Haan bilkul kar sakti hain — bas thoda dhyan rakhna hoga. Abhi activity level kaisa hai aapka?"

Tone rules:
- Use "आप" (aap) — never "tum" or "tu"
- Hindi sentence structure with English health terms (blood sugar, protein, calories, thyroid, PCOS)
- Connecting words in Hindi: "toh", "lekin", "kyunki", "isliye", "aur", "phir"
- Short, clear sentences — no long paragraphs
- Warm but professional — not overly casual
- Do NOT use: "yaar", "bhai", "chill karo", "chalo theek karte hain"

Language consistency is CRITICAL — once you start in Hinglish, stay in Hinglish for the entire reply.
INST;
    }


    private function englishInstruction(): string
    {
        return <<<'INST'
LANGUAGE RULE: The user is writing in English. Reply in English only.

NEVER mix Hindi words into an English reply.
NEVER use Devanagari or Roman Hindi.
NEVER switch language mid-response.
NEVER announce the language you are using.

Tone rules:
- Professional and warm — like a knowledgeable health coach who genuinely cares
- Short sentences, easy to read
- No formal openers, no bullet points in replies
- Empathy should be composed and respectful — not over-familiar

Language consistency is CRITICAL — once you start in English, stay in English for the entire reply.
INST;
    }

    private function tamilInstruction(): string
    {
        return 'SCRIPT RULE (CRITICAL): The user is writing in Tamil. Reply in Tamil script only. Keep the same warm, conversational coaching tone. English health terms (blood sugar, protein, calories) may be kept in English.';
    }

    private function teluguInstruction(): string
    {
        return 'SCRIPT RULE (CRITICAL): The user is writing in Telugu. Reply in Telugu script only. Keep the same warm, conversational coaching tone. English health terms (blood sugar, protein, calories) may be kept in English.';
    }

    private function marathiInstruction(): string
    {
        return 'SCRIPT RULE (CRITICAL): The user is writing in Marathi. Reply in Marathi (Devanagari script). Keep the same warm, conversational coaching tone. English health terms may be kept in English.';
    }

    private function getSwitchInstruction(string $langCode, string $script): string
    {
        $lang = str_replace('-request', '', $langCode);

        return match($lang) {
            'hindi',
            'hinglish' => $this->romanHindiInstruction()  . "\n\nThe user just asked to switch to Hindi/Hinglish. Reply in Roman Hindi (Hinglish). Switch immediately — do not announce it.",
            'tamil'    => $this->tamilInstruction()        . " The user just asked to switch to Tamil. Switch immediately.",
            'telugu'   => $this->teluguInstruction()       . " The user just asked to switch to Telugu. Switch immediately.",
            'english'  => $this->englishInstruction()      . "\n\nThe user just asked to switch to English. Switch immediately — do not announce it.",
            default    => 'Reply in the language and script the user prefers.',
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function countScriptChars(string $message, array $chars): int
    {
        $count = 0;
        foreach ($chars as $char) {
            $count += substr_count($message, $char);
        }
        return $count;
    }

    private function scoreHinglish(string $lower): int
    {
        $score = 0;
        // Check multi-word phrases first (higher confidence)
        foreach ($this->hinglishWords as $word) {
            if (str_contains($lower, $word)) {
                $score++;
                if ($score >= 2) return $score; // Early exit — confident enough
            }
        }
        return $score;
    }

    private function makeResult(string $lang, string $script): array
    {
        return ['lang' => $lang, 'script' => $script];
    }
}
