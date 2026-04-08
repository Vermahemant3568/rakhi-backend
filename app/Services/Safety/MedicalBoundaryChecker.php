<?php

namespace App\Services\Safety;

use App\Services\NLP\LanguageDetector;

class MedicalBoundaryChecker
{
    // Only block things Rakhi truly cannot do — prescribing medicine and surgery
    // Do NOT block symptoms, treatments, diet for conditions — Rakhi handles those
    private array $hardBoundaries = [
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
        'which injection',
        'kaunsa injection',
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
        $lang = $message ? $this->languageDetector->detect($message) : 'en';

        if (str_contains($lang, 'hi')) {
            return "Yaar, specific dawa ya prescription ke liye doctor se milna zaroori hai — ye main nahi bata sakti aur main nahi chahti ki galat advice doon. Lekin jo bhi diet, lifestyle, ya natural tarike se help ho sakti hai us condition mein — wo sab main kar sakti hoon. Batao kya chal raha hai, milke dekhte hain? 😊";
        }

        return "For specific medicines or prescriptions, you really do need your doctor — I don't want to get that wrong for you. But everything around it — your diet, lifestyle, natural remedies, habits — that's exactly what I'm here for. Tell me more about what's going on? 😊";
    }
}
