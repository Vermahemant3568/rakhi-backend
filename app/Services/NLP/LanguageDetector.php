<?php

namespace App\Services\NLP;

class LanguageDetector
{
    private array $hindiChars  = ['क', 'ख', 'ग', 'घ', 'ह', 'ा', 'ि', 'ी', 'ु', 'ू', 'े', 'ै', 'ो', 'ौ', 'म', 'न', 'र', 'ल', 'व', 'स'];
    private array $tamilChars  = ['அ', 'ஆ', 'இ', 'ஈ', 'உ', 'ஊ', 'எ', 'ஏ', 'ை', 'ொ', 'ோ'];
    private array $teluguChars = ['అ', 'ఆ', 'ఇ', 'ఈ', 'ఉ', 'ఊ', 'క', 'గ', 'చ', 'జ'];
    private array $marathiChars = ['अ', 'आ', 'इ', 'ई', 'उ', 'ऊ', 'क', 'ग', 'च', 'ज'];

    private array $hinglishWords = [
        'kya', 'hai', 'hain', 'mera', 'meri', 'mujhe', 'acha', 'accha', 'theek', 'thik',
        'khana', 'khaana', 'bhai', 'yaar', 'nahi', 'nahin', 'haan', 'bilkul', 'bahut',
        'thoda', 'zyada', 'abhi', 'kal', 'aaj', 'subah', 'raat', 'dopahar', 'bata',
        'batao', 'karo', 'karna', 'chahiye', 'lagta', 'lagti', 'samajh', 'pata',
        'hindi', 'hinglish', 'mein', 'main', 'tum', 'aap', 'woh', 'yeh', 'ye',
        'bolo', 'bolna', 'sunna', 'dekho', 'suno', 'please', 'zaroor',
    ];

    private array $languageRequests = [
        'hindi'    => ['hindi mein', 'hindi me', 'in hindi', 'hindi bolo', 'hindi mai', 'hindi main', 'speak hindi', 'talk hindi', 'reply hindi'],
        'hinglish' => ['hinglish', 'roman hindi', 'english mein hindi'],
        'tamil'    => ['tamil mein', 'in tamil', 'tamil la', 'tamil bolo'],
        'telugu'   => ['telugu lo', 'in telugu', 'telugu mein'],
        'english'  => ['in english', 'english mein', 'english me', 'speak english'],
    ];

    public function detect(string $message): string
    {
        // Check for explicit language switch requests first
        $lower = strtolower($message);
        foreach ($this->languageRequests as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    return $lang . '-request';
                }
            }
        }

        // Detect by script
        foreach ($this->hindiChars as $char) {
            if (str_contains($message, $char)) return 'hi';
        }
        foreach ($this->marathiChars as $char) {
            if (str_contains($message, $char)) return 'mr';
        }
        foreach ($this->tamilChars as $char) {
            if (str_contains($message, $char)) return 'ta';
        }
        foreach ($this->teluguChars as $char) {
            if (str_contains($message, $char)) return 'te';
        }

        // Detect Hinglish by word matching
        $words = explode(' ', $lower);
        $hinglishCount = 0;
        foreach ($words as $word) {
            if (in_array(trim($word, '.,!?'), $this->hinglishWords)) {
                $hinglishCount++;
            }
        }
        if ($hinglishCount >= 1) return 'hi-roman';

        return 'en';
    }

    public function getLanguageInstruction(string $langCode): string
    {
        return match(true) {
            $langCode === 'hi'                     => $this->hindiInstruction(),
            $langCode === 'hi-roman'               => $this->hinglishInstruction(),
            str_ends_with($langCode, '-request')   => $this->getLanguageRequestInstruction($langCode),
            $langCode === 'ta'                     => 'The user is writing in Tamil. Reply in Tamil. Keep the same warm, conversational tone.',
            $langCode === 'te'                     => 'The user is writing in Telugu. Reply in Telugu. Keep the same warm, conversational tone.',
            $langCode === 'mr'                     => 'The user is writing in Marathi. Reply in Marathi. Keep the same warm, conversational tone.',
            default                                => $this->englishInstruction(),
        };
    }

    private function hindiInstruction(): string
    {
        return <<<'INST'
The user is writing in Hindi (Devanagari script). Reply in Hindi.

Hindi tone rules:
- Write in clear, simple Hindi — not overly formal, but not casual slang either
- Always use "आप" — never "तुम" or "तू"
- Short sentences, easy to read
- Sound like a professional coach who genuinely cares — not a doctor's report, not a casual friend
- Do NOT translate English health terms — keep them as-is (e.g. "blood sugar", "protein", "calories")
- Empathy should be warm and composed — not over-dramatic

Good Hindi response examples:
"रात को देर से खाना और blood sugar का सीधा connection होता है। कल से dinner थोड़ा जल्दी लेने की कोशिश करें — एक हफ्ते में फर्क नज़र आएगा। आपकी शाम की routine कैसी रहती है?"
"PCOS में यह बहुत common है। Body में कोई problem नहीं है, बस थोड़ा अलग approach चाहिए। सुबह उठकर energy कैसी रहती है आपकी?"
INST;
    }

    private function hinglishInstruction(): string
    {
        return <<<'INST'
The user is writing in Hinglish — Roman Hindi mixed naturally with English. Reply in the same Hinglish style.

Tone: Professional and warm — like a knowledgeable health coach who respects the user, not a casual friend.
- Use "aap" not "tum" — always maintain respectful address
- Hindi sentence structure with English health terms where natural
- Short, clear sentences — no long paragraphs
- Health/medical terms stay in English (blood sugar, protein, calories, thyroid, PCOS)
- Connecting words in Hindi: "toh", "lekin", "kyunki", "isliye", "aur"
- Do NOT use overly casual words like "yaar", "bhai", "chill karo", "chalo isko theek karte hain"
- Empathy should be warm but composed — not dramatic or over-familiar

Good Hinglish response examples:
"Raat ko late khana aur blood sugar ka seedha connection hota hai. Kal se dinner 8 baje tak karne ki koshish karein — ek hafte mein fark nazar aayega. Aapki evening routine kaisi rehti hai?"
"PCOS mein yeh bahut common hai. Body mein koi problem nahi hai, bas thoda alag approach chahiye. Subah uthke energy kaisi rehti hai aapki?"
"Blood sugar thoda zyada hai toh ghabraiye mat — yeh manage ho sakta hai. Aaj ke khane mein kya tha, bata sakte hain?"
"Yeh fatigue usually blood sugar ke fluctuation se hoti hai. Lunch mein thoda protein shamil karein — paneer ya dal bhi kaam karega. Har meal ke baad hota hai ya kuch specific meals ke baad?"

Bad Hinglish — never do this:
"Yaar chinta mat karo, chalo isko theek karte hain."
"Acha suno, aap bilkul theek ho jaoge."
"I am understanding your concern about your blood sugar levels."
INST;
    }

    private function englishInstruction(): string
    {
        return <<<'INST'
Reply in English.

English tone rules:
- Professional and warm — like a knowledgeable health coach who genuinely cares, not a casual friend
- Short sentences, easy to read
- Do NOT sound like a health website, medical report, or AI assistant
- No formal openers, no bullet points in replies
- Empathy should be composed and respectful — not over-familiar

Good English response examples:
"Managing blood sugar with a busy schedule is genuinely hard. The one thing that helps most is keeping meal gaps under 4 hours — even a small snack counts. How does your afternoon usually look?"
"That kind of fatigue after meals is usually a blood sugar spike. Adding a small protein to your lunch can help — even a handful of nuts makes a difference. Has this been happening after every meal or just certain ones?"
INST;
    }

    private function getLanguageRequestInstruction(string $langCode): string
    {
        $lang = str_replace('-request', '', $langCode);
        return match($lang) {
            'hindi'    => $this->hindiInstruction() . "\n\nThe user just asked to switch to Hindi. Acknowledge this warmly in Hindi, then continue.",
            'hinglish' => $this->hinglishInstruction() . "\n\nThe user just asked to switch to Hinglish. Acknowledge this naturally in Hinglish, then continue.",
            'tamil'    => 'The user wants to chat in Tamil. Reply in Tamil. Keep the same warm, conversational tone.',
            'telugu'   => 'The user wants to chat in Telugu. Reply in Telugu. Keep the same warm, conversational tone.',
            'english'  => $this->englishInstruction() . "\n\nThe user just asked to switch to English. Acknowledge this naturally, then continue.",
            default    => 'Reply in the language the user prefers.',
        };
    }
}
