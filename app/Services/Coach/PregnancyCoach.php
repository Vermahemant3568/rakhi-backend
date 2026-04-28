<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class PregnancyCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId, string $inputMode = 'chat'): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $pregnancyContext = $this->buildPregnancyContext($user, $message);
        $enrichedMessage  = $this->enrichMessage($message, $pregnancyContext);

        return parent::respond($user, $enrichedMessage, $sessionId, $inputMode);
    }

    private function buildPregnancyContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $context = [];

        // --- Trimester Detection ---
        $storedCondition = strtolower($memory['health_condition'] ?? '');

        if (str_contains($lower, 'first trimester') || str_contains($lower, '1st trimester') || str_contains($lower, 'week 1') || str_contains($lower, 'week 2') || str_contains($lower, 'week 3') || str_contains($lower, 'week 4') || str_contains($lower, 'week 5') || str_contains($lower, 'week 6') || str_contains($lower, 'week 7') || str_contains($lower, 'week 8') || str_contains($lower, 'week 9') || str_contains($lower, 'week 10') || str_contains($lower, 'week 11') || str_contains($lower, 'week 12') || str_contains($storedCondition, 'first trimester')) {
            $context['trimester'] = 'first';
        } elseif (str_contains($lower, 'second trimester') || str_contains($lower, '2nd trimester') || str_contains($lower, 'week 13') || str_contains($lower, 'week 14') || str_contains($lower, 'week 20') || str_contains($lower, 'week 24') || str_contains($lower, 'week 26') || str_contains($storedCondition, 'second trimester')) {
            $context['trimester'] = 'second';
        } elseif (str_contains($lower, 'third trimester') || str_contains($lower, '3rd trimester') || str_contains($lower, 'week 27') || str_contains($lower, 'week 30') || str_contains($lower, 'week 35') || str_contains($lower, 'week 38') || str_contains($lower, 'week 40') || str_contains($storedCondition, 'third trimester')) {
            $context['trimester'] = 'third';
        } else {
            $context['trimester'] = $memory['trimester'] ?? 'unknown';
        }

        // --- Complication Signals ---
        $context['has_gestational_diabetes'] = str_contains($storedCondition, 'gestational diabetes')
            || str_contains($lower, 'gestational diabetes')
            || str_contains($lower, 'sugar in pregnancy')
            || str_contains($lower, 'pregnancy sugar');

        $context['has_bp_issue'] = str_contains($storedCondition, 'bp')
            || str_contains($storedCondition, 'blood pressure')
            || str_contains($lower, 'high bp')
            || str_contains($lower, 'low bp')
            || str_contains($lower, 'preeclampsia');

        $context['has_anaemia'] = str_contains($storedCondition, 'anaemia')
            || str_contains($storedCondition, 'anemia')
            || str_contains($lower, 'anaemia')
            || str_contains($lower, 'hemoglobin')
            || str_contains($lower, 'iron deficiency');

        // --- Topic Signals ---
        $context['asking_about_food']     = preg_match('/\b(eat|food|meal|diet|fruit|vegetable|protein|iron|calcium|folic|vitamin|khana|khaana|kya khana|kya nahi khana)\b/i', $message);
        $context['asking_about_exercise'] = preg_match('/\b(exercise|walk|yoga|workout|active|movement|vyayam|kasrat|safe exercise)\b/i', $message);
        $context['asking_about_symptoms'] = preg_match('/\b(nausea|vomit|morning sickness|ulti|dizziness|chakkar|swelling|sujan|back pain|kamar dard|cramp|bleeding|spotting|kick|movement|baby)\b/i', $message);
        $context['asking_about_weight']   = preg_match('/\b(weight|vajan|gain|bada|kitna weight|how much weight)\b/i', $message);
        $context['asking_about_sleep']    = preg_match('/\b(sleep|neend|rest|aram|insomnia|so nahi|position|kaise soye)\b/i', $message);
        $context['feeling_unwell']        = preg_match('/\b(pain|dard|tired|thakan|weak|kamzori|dizzy|chakkar|nausea|ulti|bleeding|spotting|breathless|sans)\b/i', $message);

        // --- Memory context ---
        $context['diet_habit']  = $memory['diet_habit']  ?? null;
        $context['activity']    = $memory['activity_level'] ?? null;
        $context['main_goal']   = $memory['main_goal']   ?? 'pregnancy wellness';

        return $context;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        // Core identity — always injected
        $lines[] = 'This user is PREGNANT. Every response must be pregnancy-safe. Never suggest anything that could harm the mother or baby.';

        // Trimester context
        $trimesterNote = match($ctx['trimester']) {
            'first'   => 'User is in the FIRST trimester (weeks 1-12). Common concerns: nausea, fatigue, food aversions, folic acid, avoiding harmful foods. Baby is forming organs — nutrition is critical.',
            'second'  => 'User is in the SECOND trimester (weeks 13-26). Usually the most comfortable phase. Focus: iron, calcium, gentle exercise, weight gain, baby movement.',
            'third'   => 'User is in the THIRD trimester (weeks 27-40). Focus: birth preparation, sleep positions, swelling, breathing, hospital bag, signs of labour.',
            default   => 'Trimester not confirmed. Ask naturally which month or week they are in if relevant to the question.',
        };
        $lines[] = $trimesterNote;

        // Complications
        if ($ctx['has_gestational_diabetes']) {
            $lines[] = 'User has gestational diabetes. Blood sugar control is critical. Avoid high-GI foods, suggest small frequent meals, monitor sugar levels. Safe exercise like walking helps.';
        }
        if ($ctx['has_bp_issue']) {
            $lines[] = 'User has blood pressure issues during pregnancy. Avoid high-sodium advice. Rest is important. If they mention severe headache, vision changes, or swelling — advise them to contact their doctor immediately.';
        }
        if ($ctx['has_anaemia']) {
            $lines[] = 'User has anaemia. Iron-rich foods (spinach, lentils, dates, jaggery) and vitamin C for absorption are important. Avoid tea/coffee with meals.';
        }

        // Topic-specific intelligence
        if ($ctx['asking_about_food']) {
            $foodNote = 'Pregnancy nutrition focus: folic acid (leafy greens, lentils), iron (spinach, dates, jaggery), calcium (dairy, ragi), protein (dal, eggs, paneer), omega-3 (walnuts, flaxseed). ';
            $foodNote .= 'AVOID: raw papaya, pineapple (large amounts), raw eggs, unpasteurised dairy, excess vitamin A, alcohol, high-mercury fish. ';
            if ($ctx['diet_habit']) {
                $foodNote .= "Known diet habit: {$ctx['diet_habit']}. ";
            }
            $foodNote .= 'Suggest practical Indian pregnancy-safe foods.';
            $lines[] = $foodNote;
        }

        if ($ctx['asking_about_exercise']) {
            $lines[] = 'Safe pregnancy exercises: walking, prenatal yoga, swimming, light stretching. AVOID: heavy lifting, high-impact workouts, lying flat on back after first trimester, contact sports. Always advise to check with their doctor first.';
        }

        if ($ctx['asking_about_symptoms']) {
            $lines[] = 'Acknowledge their symptom with empathy first. Common pregnancy symptoms are normal (nausea, fatigue, back pain, swelling). However, if they mention heavy bleeding, severe pain, no baby movement, severe headache, or vision changes — advise them to contact their doctor immediately. Do not alarm unnecessarily.';
        }

        if ($ctx['asking_about_weight']) {
            $lines[] = 'Healthy pregnancy weight gain: First trimester 1-2 kg total, Second trimester ~0.5 kg/week, Third trimester ~0.5 kg/week. Total depends on pre-pregnancy BMI. Focus on nutrition quality, not restriction.';
        }

        if ($ctx['asking_about_sleep']) {
            $lines[] = 'Best sleep position in pregnancy: left side (improves blood flow to baby). Use a pillow between knees. Avoid lying flat on back after 20 weeks. Short naps are fine.';
        }

        if ($ctx['feeling_unwell']) {
            $lines[] = 'User is feeling unwell. Acknowledge their discomfort warmly and with care. Ask how long this has been happening. If symptoms sound serious (heavy bleeding, severe pain, no fetal movement), gently advise them to contact their doctor or go to hospital.';
        }

        if ($ctx['activity']) {
            $lines[] = "User activity level: {$ctx['activity']}.";
        }

        $pregnancyBlock = "PREGNANCY COACHING CONTEXT (use this intelligence naturally — never sound clinical):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $pregnancyBlock . "\n\nUSER MESSAGE: " . $message;
    }
}
