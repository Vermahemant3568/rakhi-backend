<?php

namespace App\Services\Safety;

use App\Models\RakhiRule;
use App\Services\NLP\LanguageDetector;

class SafetyLayer
{
    private array $emergencyKeywords = [
        'chest pain', 'heart attack', 'can\'t breathe', 'cant breathe',
        'unconscious', 'severe bleeding', 'stroke', 'seizure', 'fainted', 'overdose',
        'sans nahi', 'behosh', 'dil mein dard', 'khoon bahut', 'neend nahi aa rahi bahut',
    ];

    private array $selfHarmKeywords = [
        'suicide', 'kill myself', 'end my life', 'self harm', 'hurt myself',
        'jeena nahi chahta', 'jeena nahi chahti', 'mar jaana chahta', 'zindagi khatam',
    ];

    public function __construct(private LanguageDetector $languageDetector) {}

    public function check(string $message): array
    {
        $lower = strtolower($message);
        $lang  = $this->languageDetector->detect($message);

        foreach ($this->emergencyKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return [
                    'is_safe'  => false,
                    'type'     => 'emergency',
                    'response' => $this->emergencyResponse($lang),
                ];
            }
        }

        foreach ($this->selfHarmKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return [
                    'is_safe'  => false,
                    'type'     => 'self_harm',
                    'response' => $this->selfHarmResponse($lang),
                ];
            }
        }

        $dbRules = RakhiRule::where('rule_type', 'safety')
                            ->where('is_active', 1)
                            ->orderBy('priority', 'desc')
                            ->get();

        foreach ($dbRules as $rule) {
            if (str_contains($lower, strtolower($rule->rule_content))) {
                return [
                    'is_safe'  => false,
                    'type'     => 'rule_violation',
                    'response' => $this->escalationResponse($lang),
                ];
            }
        }

        return ['is_safe' => true, 'type' => null, 'response' => null];
    }

    private function emergencyResponse(string $lang): string
    {
        if (str_contains($lang, 'hi')) {
            return "Yaar, ye sunke dil ghabra gaya. Abhi turant 112 pe call karo ya kisi ko paas bulao. Apna khayal rakho — main hoon yahan. 🆘";
        }
        return "Hey, this sounds serious and I'm really worried about you right now. Please call 112 immediately or get someone near you to help. You matter. 🆘";
    }

    private function selfHarmResponse(string $lang): string
    {
        if (str_contains($lang, 'hi')) {
            return "Yaar, ye sunke dil bhar aaya. Jo feel ho raha hai wo bahut heavy hai, aur main samajh sakti hoon. Please abhi iCall pe call karo: 9152987821 — woh sun'te hain, judge nahi karte. Tu akela/akeli nahi hai. 💙";
        }
        return "I hear you, and I'm really glad you said something. What you're carrying sounds incredibly heavy. Please reach out to iCall right now: 9152987821 — they listen without judgment. You are not alone in this. 💙";
    }

    private function escalationResponse(string $lang): string
    {
        if (str_contains($lang, 'hi')) {
            return "Iske liye doctor se milna zaroori lagta hai — ye meri expertise se bahar hai. Main teri diet aur lifestyle mein help kar sakti hoon, lekin is cheez ke liye please apne doctor se baat karo. 🏥";
        }
        return "This one really needs a doctor's attention — it's beyond what I can help with. I'm here for your diet and lifestyle, but please see your doctor for this. 🏥";
    }
}
