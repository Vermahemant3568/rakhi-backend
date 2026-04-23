<?php

namespace App\Services\Safety;

use App\Services\NLP\LanguageDetector;

class MedicalBoundaryChecker
{
    // Only block things Rakhi truly cannot do — prescribing medicine, insulin dosing, and surgery
    // Do NOT block symptoms, treatments, diet for conditions — Rakhi handles those
    private array $hardBoundaries = [
        // Prescription requests
        'prescribe me',
        'write a prescription',
        'which tablet should i take',
        'which medicine should i take',
        'what is the dosage',
        'how many mg',
        'kaunsi tablet loon',
        'kaunsi dawa loon',
        'dawa batao',
        'medicine likho',
        'surgery ke baare mein',
        'operation karna chahiye',
        'should i get surgery',
        // Insulin dosing — hard block regardless of type
        'which injection',
        'kaunsa injection',
        'kitna insulin',
        'insulin dose',
        'insulin kitni loon',
        'insulin kitna loon',
        'how much insulin',
        'insulin units',
        'basal dose',
        'bolus dose',
        'correction dose',
        'sliding scale',
        'carb ratio',
        'insulin to carb',
        'insulin sensitivity',
        'isf insulin',
        'correction factor',
        'increase insulin',
        'decrease insulin',
        'insulin badhaon',
        'insulin kam karun',
        'insulin adjust',
    ];

    public function __construct(private LanguageDetector $languageDetector) {}

    public function check(string $message): bool
    {
        $lower = strtolower($message);
        foreach ($this->hardBoundaries as $term) {
            if (str_contains($lower, $term)) return true;
        }
        return false;
    }

    public function getBoundaryResponse(string $message = ''): string
    {
        $lang  = $message ? $this->languageDetector->detect($message) : 'en';
        $lower = strtolower($message);

        // Insulin-specific response — warmer and more clinically honest
        $isInsulinQuery = preg_match('/insulin|basal|bolus|correction|sliding|carb ratio|units/i', $lower);

        if ($isInsulinQuery) {
            if (str_contains($lang, 'hi')) {
                return "Insulin ki exact dose ke baare mein main aapko guide nahi kar sakti — yeh sirf aapke doctor ya diabetologist hi safely bata sakte hain, kyunki yeh aapke sugar readings, weight, aur body response pe depend karta hai. 💛\n\nLekin jo main kar sakti hoon — aapke khane ka timing, kya khayein, exercise, aur sugar control ke natural tarike — woh sab mujhse poochh sakte ho. Kya abhi koi aur cheez chal rahi hai?";
            }
            return "Insulin dosing is something only your doctor or diabetologist can safely guide you on — it depends on your specific readings, weight, and how your body responds. I don't want to get that wrong for you. 💛\n\nWhat I can help with is your food timing, what to eat, exercise, and natural ways to support your sugar control. Is there something else going on right now?";
        }

        if (str_contains($lang, 'hi')) {
            return "Specific dawa ya prescription ke liye doctor se milna zaroori hai — yeh main nahi bata sakti aur main nahi chahti ki galat advice doon. Lekin jo bhi diet, lifestyle, ya natural tarike se help ho sakti hai us condition mein — wo sab main kar sakti hoon. Batao kya chal raha hai, milke dekhte hain? 😊";
        }

        return "For specific medicines or prescriptions, you really do need your doctor — I don't want to get that wrong for you. But everything around it — your diet, lifestyle, natural remedies, habits — that's exactly what I'm here for. Tell me more about what's going on? 😊";
    }
}
