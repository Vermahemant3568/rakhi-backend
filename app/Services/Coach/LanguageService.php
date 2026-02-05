<?php

namespace App\Services\Coach;

class LanguageService
{
    public function detect(string $text): array
    {
        $text = strtolower(trim($text));
        
        // Detect language mix and style
        $analysis = [
            'primary_language' => 'english',
            'style' => 'formal',
            'hinglish_level' => 0,
            'hindi_words' => [],
            'tone' => 'neutral',
            'formality' => 'formal'
        ];
        
        // Hindi word detection
        $hindiWords = $this->detectHindiWords($text);
        $analysis['hindi_words'] = $hindiWords;
        $analysis['hinglish_level'] = count($hindiWords);
        
        // Language classification
        if ($this->isPureHindi($text)) {
            $analysis['primary_language'] = 'hindi';
        } elseif ($analysis['hinglish_level'] >= 3) {
            $analysis['primary_language'] = 'hinglish';
        } elseif ($analysis['hinglish_level'] >= 1) {
            $analysis['primary_language'] = 'mixed';
        }
        
        // Style detection
        $analysis['style'] = $this->detectStyle($text);
        $analysis['formality'] = $this->detectFormality($text);
        $analysis['tone'] = $this->detectTone($text);
        
        return $analysis;
    }
    
    public function getResponseInstruction(array $languageAnalysis): string
    {
        $instruction = "PROFESSIONAL LANGUAGE GUIDELINES:\n";
        $instruction .= "Role: You are Rakhi, a professional and empathetic health/wellness assistant.\n";
        $instruction .= "Tone: Calm, polite, and professional. Use respectful language (prefer 'Aap' over 'Tum').\n\n";
        
        $instruction .= "STRICT GUIDELINES:\n";
        $instruction .= "- Remove Informal Fillers: Do not use words like 'Arre,' 'Yaar,' or 'Chalo'\n";
        $instruction .= "- Structure: Provide clear, helpful advice without being overly chatty\n";
        $instruction .= "- Language: Professional Hinglish (Hindi + English) in ROMAN SCRIPT ONLY\n";
        $instruction .= "- NEVER use Devanagari script - always use Roman letters\n";
        $instruction .= "- Use supportive emojis sparingly (max 1-2 per response)\n";
        $instruction .= "- Focus on user's well-being with disciplined and mature approach\n";
        $instruction .= "- Be specific and actionable in advice\n\n";
        
        switch ($languageAnalysis['primary_language']) {
            case 'hinglish':
            case 'mixed':
                $instruction .= "LANGUAGE STYLE:\n";
                $instruction .= "- Use professional Hinglish mixing Hindi and English naturally\n";
                $instruction .= "- Always use 'Aap' instead of 'tum' for respect\n";
                $instruction .= "- Use words like: acha, thik hai, zaroor, bilkul\n";
                $instruction .= "- Avoid: yaar, bhai, arre, oye, chalo\n";
                break;
                
            case 'hindi':
                $instruction .= "LANGUAGE STYLE:\n";
                $instruction .= "- Reply in professional Hinglish (Roman script Hindi mixed with English)\n";
                $instruction .= "- Do NOT use Devanagari script\n";
                $instruction .= "- Always use 'Aap' for respect\n";
                break;
                
            default:
                $instruction .= "LANGUAGE STYLE:\n";
                $instruction .= "- Reply in professional English\n";
                $instruction .= "- Keep tone warm but professional\n";
        }
        
        return $instruction;
    }
    
    protected function detectHindiWords(string $text): array
    {
        $hindiWords = [
            // Common Hinglish words
            'haan', 'nahi', 'kya', 'hai', 'ho', 'kar', 'karo', 'main', 'mai', 'mujhe', 'aap', 'apko', 'apka', 'apna',
            'acha', 'accha', 'thik', 'theek', 'bas', 'bhi', 'toh', 'to', 'se', 'mein', 'me', 'hu', 'hoon',
            'aur', 'ya', 'koi', 'kuch', 'sab', 'sabko', 'sabse', 'woh', 'wo', 'yeh', 'ye', 'kse', 'kaise',
            'kal', 'aaj', 'abhi', 'phir', 'fir', 'jab', 'tab', 'kab', 'kahan', 'kyun', 'kyu', 'kr', 'karna',
            'kaun', 'kaisa', 'kaise', 'kitna', 'kitne', 'jyada', 'zyada', 'kam', 'bohot', 'bahut',
            'thoda', 'bilkul', 'ekdum', 'sach', 'galat', 'sahi', 'ghar', 'office', 'zaroor',
            'paani', 'pani', 'khana', 'khaana', 'peena', 'sona', 'uthna', 'jaana', 'jana', 'sakta', 'sakti',
            'aana', 'ana', 'dekho', 'dekh', 'suno', 'sun', 'bolo', 'bol', 'samjha', 'samajh',
            'pata', 'malum', 'lagta', 'lagti', 'hota', 'hoti', 'chahiye', 'chaahiye', 'manage',
            'shayad', 'ji', 'sahab', 'sir', 'madam', 'sugar', 'diabetes',
            // Food related
            'roti', 'chawal', 'dal', 'sabzi', 'doodh', 'chai', 'coffee', 'paani', 'juice',
            // Time related
            'subah', 'dopahar', 'shaam', 'raat', 'din', 'hafta', 'mahina', 'saal'
        ];
        
        $foundWords = [];
        foreach ($hindiWords as $word) {
            if (str_contains($text, $word)) {
                $foundWords[] = $word;
            }
        }
        
        return array_unique($foundWords);
    }
    
    protected function isPureHindi(string $text): bool
    {
        return preg_match('/[\x{0900}-\x{097F}]/u', $text) > 0;
    }
    
    protected function detectStyle(string $text): string
    {
        $professionalWords = ['aap', 'apko', 'apka', 'ji', 'sir', 'madam', 'zaroor', 'bilkul'];
        $professionalCount = 0;
        
        foreach ($professionalWords as $word) {
            if (str_contains($text, $word)) {
                $professionalCount++;
            }
        }
        
        return $professionalCount >= 1 ? 'professional' : 'casual';
    }
    
    protected function detectFormality(string $text): string
    {
        $formalWords = ['please', 'thank you', 'sir', 'madam', 'aap', 'apko', 'apka', 'ji', 'zaroor'];
        $informalWords = ['kro', 'tum', 'tumhe', 'tera'];
        
        $formalCount = 0;
        $informalCount = 0;
        
        foreach ($formalWords as $word) {
            if (str_contains($text, $word)) $formalCount++;
        }
        
        foreach ($informalWords as $word) {
            if (str_contains($text, $word)) $informalCount++;
        }
        
        return $formalCount >= $informalCount ? 'formal' : 'informal';
    }
    
    protected function detectTone(string $text): string
    {
        if (preg_match('/[😊🙂😄😃🤗👍❤️💙]/', $text)) {
            return 'friendly';
        }
        
        $friendlyWords = ['thanks', 'thank you', 'please', 'love', 'great', 'awesome', 'nice'];
        foreach ($friendlyWords as $word) {
            if (str_contains($text, $word)) {
                return 'friendly';
            }
        }
        
        return 'neutral';
    }
}