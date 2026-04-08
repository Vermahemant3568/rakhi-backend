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
            $langCode === 'hi'             => 'The user is writing in Hindi (Devanagari script). Reply in Hindi using Devanagari script.',
            $langCode === 'hi-roman'       => 'The user is writing in Hinglish (Hindi in Roman/English letters). Reply in the same Hinglish style — casual, warm, Roman Hindi mixed with English naturally.',
            str_ends_with($langCode, '-request') => $this->getLanguageRequestInstruction($langCode),
            $langCode === 'ta'             => 'The user is writing in Tamil. Reply in Tamil.',
            $langCode === 'te'             => 'The user is writing in Telugu. Reply in Telugu.',
            $langCode === 'mr'             => 'The user is writing in Marathi. Reply in Marathi.',
            default                        => 'Reply in English.',
        };
    }

    private function getLanguageRequestInstruction(string $langCode): string
    {
        $lang = str_replace('-request', '', $langCode);
        return match($lang) {
            'hindi'    => 'The user wants to chat in Hindi. From now on reply in Hindi (Devanagari script). Acknowledge this warmly.',
            'hinglish' => 'The user wants to chat in Hinglish. Reply in casual Hinglish (Roman Hindi mixed with English).',
            'tamil'    => 'The user wants to chat in Tamil. Reply in Tamil.',
            'telugu'   => 'The user wants to chat in Telugu. Reply in Telugu.',
            'english'  => 'The user wants to chat in English. Reply in English.',
            default    => 'Reply in the language the user prefers.',
        };
    }
}
