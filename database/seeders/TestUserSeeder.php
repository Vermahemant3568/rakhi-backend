<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSubscription;
use App\Models\Language;
use App\Models\Goal;
use Carbon\Carbon;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create test user
        $user = User::create([
            'mobile' => '9999999999',
            'country_code' => '+91',
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_onboarded' => true,
        ]);

        // Create user profile
        UserProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'gender' => 'male',
            'dob' => '1990-01-01',
            'height_cm' => 170.0,
            'weight_kg' => 70.0,
        ]);

        // Add user languages (assuming language IDs 1,2 exist)
        if (Language::count() > 0) {
            $user->languages()->attach([1, 2]);
        }

        // Add user goals (assuming goal IDs 1,2 exist)
        if (Goal::count() > 0) {
            $user->goals()->attach([1, 2]);
        }

        // Create trial subscription
        UserSubscription::create([
            'user_id' => $user->id,
            'status' => 'trial',
            'trial_start' => now(),
            'trial_end' => now()->addDays(7),
            'payment_provider' => 'razorpay'
        ]);
    }
}