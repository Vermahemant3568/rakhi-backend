<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Language;
use App\Models\UserGoal;
use App\Models\UserCoach;
use App\Models\UserMemory;
use App\Services\Coach\CoachRouter;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(private CoachRouter $coachRouter) {}

    // Screen 5 — Get active languages
    public function languages()
    {
        return response()->json([
            'success'   => true,
            'languages' => Language::where('is_active', 1)
                            ->orderBy('sort_order')
                            ->get(),
        ]);
    }

    // Screen 5 — Save language
    public function saveLanguage(Request $request)
    {
        $request->validate([
            'language_id' => 'required|exists:languages,id',
        ]);

        auth()->user()->update([
            'language_id'    => $request->language_id,
            'onboarding_step'=> 5,
        ]);

        return $this->success('Language saved');
    }

    // Screen 6 — Basic info
    public function saveBasicInfo(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'gender'     => 'required|in:male,female,other',
        ]);

        auth()->user()->update([
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'gender'         => $request->gender,
            'onboarding_step'=> 6,
        ]);

        return $this->success('Basic info saved');
    }

    // Screen 7 — Date of birth
    public function saveDob(Request $request)
    {
        $request->validate([
            'date_of_birth' => 'required|date|before:today',
        ]);

        auth()->user()->update([
            'date_of_birth'  => $request->date_of_birth,
            'onboarding_step'=> 7,
        ]);

        return $this->success('Date of birth saved');
    }

    // Screen 8 — Weight
    public function saveWeight(Request $request)
    {
        $request->validate([
            'weight' => 'required|numeric|min:20|max:300',
        ]);

        auth()->user()->update([
            'weight'         => $request->weight,
            'onboarding_step'=> 8,
        ]);

        return $this->success('Weight saved');
    }

    // Screen 9 — Height
    public function saveHeight(Request $request)
    {
        $request->validate([
            'height' => 'required|numeric|min:100|max:250',
        ]);

        auth()->user()->update([
            'height'         => $request->height,
            'onboarding_step'=> 9,
        ]);

        return $this->success('Height saved');
    }

    // Screen 10 — Goals
    public function goals()
    {
        return response()->json([
            'success' => true,
            'goals'   => Goal::where('is_active', 1)
                            ->orderBy('sort_order')
                            ->get(),
        ]);
    }

    public function saveGoals(Request $request)
    {
        $request->validate([
            'goal_ids'   => 'required|array|min:1',
            'goal_ids.*' => 'exists:goals,id',
        ]);

        $user = auth()->user();

        // Remove old goals
        UserGoal::where('user_id', $user->id)->delete();

        // Save new goals
        foreach ($request->goal_ids as $goalId) {
            UserGoal::create([
                'user_id' => $user->id,
                'goal_id' => $goalId,
            ]);
        }

        // Auto assign coaches based on goals
        $this->coachRouter->assignCoaches($user, $request->goal_ids);

        $user->update(['onboarding_step' => 10]);

        return $this->success('Goals saved and coaches assigned');
    }

    // Screen 11 — Notifications
    public function saveNotification(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        auth()->user()->update([
            'notification_enabled' => $request->enabled,
            'onboarding_step'      => 11,
        ]);

        return $this->success('Notification preference saved');
    }

    // Screen 12 — Microphone
    public function saveMicrophone(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        auth()->user()->update([
            'microphone_enabled' => $request->enabled,
            'onboarding_step'    => 12,
        ]);

        return $this->success('Microphone preference saved');
    }

    // Screen 17 — Camera (Meal Vision)
    public function saveCamera(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        auth()->user()->update([
            'camera_enabled'  => $request->enabled,
            'onboarding_step' => 13,
        ]);

        return $this->success('Camera preference saved');
    }

    // Onboarding status — Flutter uses this to
    // know which screen to resume from
    public function status()
    {
        $user = auth()->user()->load([
            'language', 'goals', 'subscription'
        ]);

        return response()->json([
            'success'              => true,
            'onboarding_complete'  => $user->onboarding_complete,
            'onboarding_step'      => $user->onboarding_step,
            'user'                 => $user,
        ]);
    }

    // Complete onboarding — Screen 13 → 16
    public function completeOnboarding()
    {
        $user = auth()->user()->load(['goals', 'language']);

        $user->update([
            'onboarding_complete'  => 1,
            'onboarding_step'      => 16,
            'consultation_state'   => 'pending',
        ]);

        // Seed UserMemory from onboarding data so Rakhi knows the user from day 1
        $this->seedOnboardingMemory($user);

        return $this->success('Onboarding complete! Welcome to Rakhi.');
    }

    private function seedOnboardingMemory($user): void
    {
        $user->loadMissing(['goals', 'language']);

        $goalName = strtolower($user->goals->pluck('name')->first() ?? '');

        // Map goal name to health condition
        $condition = match(true) {
            str_contains($goalName, 'diabet')  => 'diabetes',
            str_contains($goalName, 'pcos')    => 'PCOS',
            str_contains($goalName, 'thyroid') => 'thyroid condition',
            str_contains($goalName, 'pregnan') => 'pregnancy',
            str_contains($goalName, 'weight')  => 'weight management',
            str_contains($goalName, 'stress')  => 'stress management',
            str_contains($goalName, 'sleep')   => 'sleep issues',
            str_contains($goalName, 'energy')  => 'low energy',
            str_contains($goalName, 'habit')   => 'habit building',
            str_contains($goalName, 'mental')  => 'mental wellness',
            default                            => 'general wellness',
        };

        $seeds = [
            'main_goal'        => $user->goals->pluck('name')->join(', ') ?: 'general wellness',
            'health_condition' => $condition,
        ];

        // Add physical stats if available
        if ($user->weight) {
            $seeds['lifestyle'] = 'weight: ' . $user->weight . 'kg'
                . ($user->height ? ', height: ' . $user->height . 'cm' : '')
                . ($user->getAge() > 0 ? ', age: ' . $user->getAge() : '')
                . ($user->gender ? ', gender: ' . $user->gender : '');
        }

        foreach ($seeds as $key => $value) {
            if (!empty($value)) {
                UserMemory::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    ['value' => $value, 'source' => 'onboarding']
                );
            }
        }
    }

    // FAQ data — Screen 14
    public function faq()
    {
        return response()->json([
            'success' => true,
            'faqs'    => [
                [
                    'question' => 'Is Rakhi a doctor?',
                    'answer'   => 'No. Rakhi is a wellness and lifestyle coach. She does not diagnose or prescribe.',
                ],
                [
                    'question' => 'Is my data safe?',
                    'answer'   => 'Yes. All your data is encrypted and stored securely.',
                ],
                [
                    'question' => 'Can I cancel anytime?',
                    'answer'   => 'Yes. You can cancel your subscription anytime from settings.',
                ],
                [
                    'question' => 'What languages does Rakhi support?',
                    'answer'   => 'Rakhi supports Hindi, English, Tamil, Telugu and more.',
                ],
            ],
        ]);
    }

    // Helper
    private function success(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
