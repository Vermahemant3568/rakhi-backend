<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Models\Language;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function getLanguages()
    {
        $languages = Language::where('is_active', true)->get(['id', 'name', 'code']);
        
        return response()->json([
            'success' => true,
            'data' => $languages
        ]);
    }

    public function getGoals()
    {
        $goals = Goal::where('is_active', true)->get(['id', 'title as name', 'description']);
        
        return response()->json([
            'success' => true,
            'data' => $goals
        ]);
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,other',
            'dob' => 'required|date',
            'height_cm' => 'required|numeric|min:50|max:300',
            'weight_kg' => 'required|numeric|min:20|max:500',
            'language_id' => 'required|integer|exists:languages,id',
            'goal_ids' => 'required|array|min:1',
            'goal_ids.*' => 'integer|exists:goals,id'
        ]);

        $user = $request->user();

        // Create or update user profile
        $profile = $user->profile()->updateOrCreate([], [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'],
            'dob' => $validated['dob'],
            'height_cm' => $validated['height_cm'],
            'weight_kg' => $validated['weight_kg'],
        ]);

        // Set user language
        $user->languages()->sync([$validated['language_id']]);

        // Set user goals
        $user->goals()->sync($validated['goal_ids']);

        // Mark user as onboarded
        $user->update(['is_onboarded' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed successfully'
        ]);
    }
}
