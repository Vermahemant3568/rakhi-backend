<?php

namespace App\Services\Voice;

/**
 * VoiceCallBehavior — all human-call phrases in one place.
 *
 * Every phrase is short, warm, and designed to sound like a real person
 * on a phone call — not a bot reading a script.
 */
class VoiceCallBehavior
{
    // ─── 1. Audibility check — appended to every call-start greeting ──────────

    public function audibilityCheck(string $lang): string
    {
        return match(true) {
            $this->isHindi($lang) => 'Kya aap mujhe clearly sun pa rahe hain?',
            $lang === 'ta'        => 'Naan solvathu tெளிவாக கேக்கிறீர்களா?',
            $lang === 'te'        => 'Meeru naanu clearly vinagalugutunnara?',
            default               => 'Can you hear me clearly?',
        };
    }

    // ─── 2. Listening acknowledgements — brief, human, varied ────────────────

    public function listeningAck(string $lang): string
    {
        $hindiAcks = ['Hmm.', 'Haan.', 'Achha.', 'Samajh aa raha hai.', 'Theek hai.'];
        $englishAcks = ['Mm-hmm.', 'Okay.', 'I see.', 'Got it.', 'Right.'];

        $pool = $this->isHindi($lang) ? $hindiAcks : $englishAcks;
        return $pool[array_rand($pool)];
    }

    // ─── 3. Silence / no response detected ───────────────────────────────────

    public function silencePrompt(string $lang): string
    {
        return match(true) {
            $this->isHindi($lang) => 'Hello? Kya aap abhi bhi wahan hain?',
            $lang === 'ta'        => 'Hello? Neenga inga irukkeengala?',
            $lang === 'te'        => 'Hello? Meeru ikkade unnara?',
            default               => 'Hello? Are you still there?',
        };
    }

    // ─── 4. Not heard / STT failed — first attempt ───────────────────────────

    public function notHeardOnce(string $lang): string
    {
        return match(true) {
            $this->isHindi($lang) => 'Shayad main aapko clearly nahi sun pa rahi — ek baar phir bolenge?',
            $lang === 'ta'        => 'Sari kekkavillai — oru thadavai solluveengala?',
            $lang === 'te'        => 'Sari vinagaledhu — okasari cheppagalara?',
            default               => "I couldn't catch that properly — could you say it again?",
        };
    }

    // ─── 5. Not heard — second consecutive failure ────────────────────────────

    public function notHeardTwice(string $lang): string
    {
        return match(true) {
            $this->isHindi($lang) => 'Lagta hai thodi awaaz ki problem hai — thoda aur clearly bolenge?',
            default               => "Still having a little trouble hearing you — could you speak a bit louder?",
        };
    }

    // ─── 6. Repeated failures — suggest switching to chat ────────────────────

    public function networkFallbackSuggestion(string $lang): string
    {
        return match(true) {
            $this->isHindi($lang) => 'Shayad network ki wajah se awaaz nahi aa rahi. Koi baat nahi — hum chat pe continue kar lete hain, wahan sab kuch same rahega.',
            default               => "Seems like there's a connection issue. No worries — let's continue in chat, everything will be right where we left off.",
        };
    }

    // ─── 7. Pre-farewell clarity confirmation ────────────────────────────────

    public function farewellClarityCheck(string $lang): string
    {
        return match(true) {
            $this->isHindi($lang) => 'Umeed hai meri awaaz aapko clearly aa rahi thi. Agar koi baat reh gayi ho toh chat mein poochh saktey hain — main wahan bhi hoon.',
            default               => "I hope everything came through clearly on your end. If anything's left, just continue in chat — I'll be right there.",
        };
    }

    // ─── 8. Full farewell (clarity check + goodbye) ──────────────────────────

    public function farewell(string $lang): string
    {
        $clarity = $this->farewellClarityCheck($lang);

        $goodbye = match(true) {
            $this->isHindi($lang) => 'Apna khayal rakhein. Bye!',
            default               => 'Take care. Bye!',
        };

        return $clarity . ' ' . $goodbye;
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function isHindi(string $lang): bool
    {
        return str_starts_with($lang, 'hi') || $lang === 'hi-roman';
    }
}
