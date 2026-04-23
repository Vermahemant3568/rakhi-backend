<?php

namespace App\Services\Safety;

use App\Models\RakhiRule;
use App\Services\NLP\LanguageDetector;

class SafetyLayer
{
    private array $emergencyKeywords = [
        // General emergencies
        'chest pain', 'heart attack', 'can\'t breathe', 'cant breathe',
        'unconscious', 'severe bleeding', 'stroke', 'seizure', 'fainted', 'overdose',
        'sans nahi', 'behosh', 'dil mein dard', 'khoon bahut', 'neend nahi aa rahi bahut',
        // Severe hypoglycemia (low sugar emergency)
        'sugar bahut low', 'sugar 40', 'sugar 50', 'glucose 40', 'glucose 50',
        'behosh ho gaya sugar', 'sugar se behosh', 'shaking badly', 'can\'t stand sugar',
        'hypoglycemia severe', 'severe low sugar', 'passed out sugar',
        // DKA signals (Type 1 emergency — Diabetic Ketoacidosis)
        'fruity breath', 'breath smell sweet', 'vomiting sugar', 'ulti sugar',
        'sugar 400 vomiting', 'sugar 500 vomiting', 'sugar 400 ulti', 'sugar 500 ulti',
        'dka', 'diabetic ketoacidosis', 'ketones high', 'ketone strip positive',
        'sugar 400 weakness', 'sugar 500 weakness', 'sugar 400 kamzori',
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
            return "Yeh sunke dil ghabra gaya — yeh serious lag raha hai. Abhi turant 112 pe call karo ya kisi ko paas bulao. Agar sugar bahut low hai toh abhi kuch meetha lo — juice, glucose, ya sugar — aur phir doctor ko call karo. Apna khayal rakho — main hoon yahan. 🆘";
        }
        return "This sounds serious and I'm really worried about you right now. Please call 112 immediately or get someone near you. If your sugar is very low, take something sweet right now — juice, glucose, or sugar — then call your doctor. You matter. 🆘";
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
