<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\UserMemory;

class VisionCoach extends BaseCoach
{
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $ctx             = $this->buildContext($user, $message);
        $enrichedMessage = $this->enrichMessage($message, $ctx);

        return parent::respond($user, $enrichedMessage, $sessionId);
    }

    private function buildContext(User $user, string $message): array
    {
        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lower   = strtolower($message);
        $ctx     = [];

        $healthCond = strtolower($memory['health_condition'] ?? '');
        $ctx['has_diabetes']   = str_contains($healthCond, 'diabet') || str_contains($lower, 'diabet');
        $ctx['has_bp']         = str_contains($healthCond, 'blood pressure') || str_contains($lower, 'hypertension');
        $ctx['wears_glasses']  = preg_match('/\b(glasses|spectacles|chasma|contact lens|power|minus|plus|myopia|hyperopia)\b/i', $message);

        $ctx['eye_strain']     = preg_match('/\b(eye strain|strain|tired eyes|burning|itching|red eyes|aankhein jal|aankhein thak)\b/i', $message);
        $ctx['screen_time']    = preg_match('/\b(screen|phone|laptop|computer|mobile|tv|digital|hours on screen)\b/i', $message);
        $ctx['dry_eyes']       = preg_match('/\b(dry eyes|dryness|aankhein sukhi|irritation|gritty|sandy feeling)\b/i', $message);
        $ctx['blurry_vision']  = preg_match('/\b(blurry|blur|dhundhla|vision problem|can.t see clearly|vision weak)\b/i', $message);
        $ctx['headache']       = preg_match('/\b(headache|sar dard|migraine|eye headache|after screen)\b/i', $message);
        $ctx['night_vision']   = preg_match('/\b(night vision|dark|raat ko nahi dikta|driving night|night blind)\b/i', $message);
        $ctx['floaters']       = preg_match('/\b(floater|spots|flashes|dark spot|cobweb|floating)\b/i', $message);

        $ctx['asking_about_exercises'] = preg_match('/\b(exercise|yoga|palming|20-20-20|eye exercise|aankhon ki kasrat)\b/i', $message);
        $ctx['asking_about_diet']      = preg_match('/\b(food|diet|eat|vitamin|nutrition|kya khana|aankhon ke liye)\b/i', $message);
        $ctx['asking_about_screen']    = preg_match('/\b(reduce screen|screen time|blue light|filter|protect eyes)\b/i', $message);
        $ctx['asking_about_power']     = preg_match('/\b(power increase|power badh|minus badh|can power reduce|improve eyesight)\b/i', $message);

        $ctx['activity']   = $memory['activity_level'] ?? $user->activity_level ?? null;
        $ctx['lifestyle']  = $memory['lifestyle']      ?? null;
        $ctx['age']        = $user->getAge() > 0 ? $user->getAge() : null;

        return $ctx;
    }

    private function enrichMessage(string $message, array $ctx): string
    {
        $lines = [];

        $lines[] = 'You are an eye health and vision coach. You provide evidence-based guidance on eye care, screen health, nutrition for eyes, and lifestyle habits. You are NOT an ophthalmologist — for any vision changes, pain, or sudden symptoms, always recommend seeing an eye doctor. Be warm and practical.';

        if ($ctx['has_diabetes']) {
            $lines[] = 'IMPORTANT: User has diabetes. Diabetic retinopathy is a serious complication — high blood sugar damages retinal blood vessels. Annual dilated eye exam is essential. Blurry vision can be a sign of blood sugar fluctuation OR retinopathy. Controlling blood sugar is the most important thing for eye health in diabetics.';
        }

        if ($ctx['has_bp']) {
            $lines[] = 'IMPORTANT: User has high blood pressure. Hypertensive retinopathy can damage eye blood vessels. Regular eye exams are important. Controlling BP protects vision long-term.';
        }

        if ($ctx['eye_strain'] || $ctx['screen_time']) {
            $lines[] = 'Digital eye strain (Computer Vision Syndrome): caused by prolonged screen use. The 20-20-20 rule: every 20 minutes, look at something 20 feet away for 20 seconds. Blink consciously — screen use reduces blink rate by 60%, causing dryness. Screen should be at arm\'s length, slightly below eye level. Increase font size rather than leaning forward. Blue light glasses have limited evidence but reducing screen brightness helps.';
        }

        if ($ctx['dry_eyes']) {
            $lines[] = 'Dry eyes: caused by reduced blinking (screens), air conditioning, fans, contact lenses, or certain medications. Artificial tears (preservative-free) are safe for regular use. Omega-3 (walnuts, flaxseed, fish) improves tear quality. Blink exercises: every hour, close eyes for 20 seconds and squeeze gently. Humidifier in AC rooms helps. If severe, see an ophthalmologist.';
        }

        if ($ctx['blurry_vision']) {
            $lines[] = 'Blurry vision: can be caused by refractive error (needs glasses/updated prescription), dry eyes, blood sugar fluctuation (diabetics), fatigue, or more serious conditions. If sudden onset, see a doctor immediately. If gradual, schedule an eye exam. Do not ignore persistent blurry vision.';
        }

        if ($ctx['floaters']) {
            $lines[] = 'Floaters: small spots or cobweb-like shapes in vision. Common and usually harmless (vitreous floaters). HOWEVER: sudden increase in floaters, flashes of light, or a curtain/shadow in vision = EMERGENCY — could be retinal detachment. Advise seeing an ophthalmologist immediately if sudden onset.';
        }

        if ($ctx['night_vision']) {
            $lines[] = 'Poor night vision: can be caused by Vitamin A deficiency (most common), myopia, or cataracts. Vitamin A rich foods: carrots, sweet potato, spinach, eggs, dairy. If severe or worsening, see an ophthalmologist.';
        }

        if ($ctx['asking_about_exercises']) {
            $lines[] = 'Eye exercises: 1) 20-20-20 rule (every 20 min, look 20 feet away for 20 sec). 2) Palming (rub hands warm, cup over closed eyes for 1 min — relaxes eye muscles). 3) Focus shifting (near-far alternation — hold finger 6 inches away, focus, then focus on distant object, repeat 10x). 4) Eye rolling (slow circles). These reduce strain but do NOT improve refractive error (power). Yoga poses that increase blood flow to head (like forward bends) benefit eye health.';
        }

        if ($ctx['asking_about_diet']) {
            $lines[] = 'Eye nutrition: Vitamin A (carrots, sweet potato, spinach, eggs) — prevents night blindness. Lutein & Zeaxanthin (leafy greens, eggs, corn) — protect macula, reduce cataract risk. Omega-3 (walnuts, flaxseed, fish) — reduces dry eyes and macular degeneration risk. Vitamin C (amla, guava, lemon) — reduces cataract risk. Zinc (pumpkin seeds, dal, nuts) — supports retinal health. Antioxidants from colourful vegetables protect against oxidative damage to eyes.';
        }

        if ($ctx['asking_about_screen']) {
            $lines[] = 'Protecting eyes from screens: 1) 20-20-20 rule. 2) Screen brightness = ambient light level (not brighter). 3) Night mode/warm colour temperature after sunset. 4) Matte screen protector reduces glare. 5) Blink consciously. 6) Artificial tears if dry. 7) Screen at arm\'s length, slightly below eye level. 8) Take a 5-min screen break every hour. Blue light glasses: limited evidence but reducing overall screen time is more effective.';
        }

        if ($ctx['asking_about_power']) {
            $lines[] = 'Eye power (refractive error): myopia (minus power) cannot be reversed with exercises — this is a structural change in the eye. However, progression can be slowed: outdoor time (2 hours/day) significantly slows myopia progression in children and young adults. Orthokeratology, atropine drops, and myopia control lenses are medical options — suggest consulting an ophthalmologist. Maintaining good eye habits prevents worsening.';
        }

        if ($ctx['headache']) {
            $lines[] = 'Eye-related headaches: often caused by uncorrected refractive error (needs glasses or updated prescription), eye strain from screens, or squinting. If headaches are frequent, an eye exam is the first step. Ensure glasses prescription is current. Reduce screen time and apply 20-20-20 rule.';
        }

        if ($ctx['lifestyle']) {
            $lines[] = "User lifestyle: {$ctx['lifestyle']}. Tailor eye care advice to their actual daily routine.";
        }

        if ($ctx['age'] && $ctx['age'] >= 40) {
            $lines[] = 'User 40+: presbyopia (difficulty reading close up) is normal after 40 — the lens loses flexibility. Reading glasses or progressive lenses help. Also increased risk of glaucoma, cataracts, and macular degeneration — annual eye exam is essential after 40. Lutein and zeaxanthin supplementation is beneficial.';
        }

        $block = "EYE HEALTH COACHING CONTEXT (practical, evidence-based eye care — always recommend seeing an ophthalmologist for vision changes or pain):\n"
            . implode("\n", array_map(fn($l) => "- {$l}", $lines));

        return $block . "\n\nUSER MESSAGE: " . $message;
    }
}
