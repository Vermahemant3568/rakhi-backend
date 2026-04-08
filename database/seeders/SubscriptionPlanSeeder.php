<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('subscription_plans')->insertOrIgnore([
            [
                'name'             => 'Monthly Plan',
                'duration_days'    => 30,
                'price'            => 499.00,
                'discounted_price' => 399.00,
                'trial_days'       => 7,
                'features'         => json_encode([
                    'Unlimited AI Coach Chat',
                    'Voice Coaching',
                    'Meal Vision (Camera)',
                    'Personalized Diet Plan',
                    'Personalized Fitness Plan',
                    'Daily Check-in & Streak',
                    'Progress Tracking',
                ]),
                'is_active'        => 1,
                'sort_order'       => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'name'             => 'Quarterly Plan',
                'duration_days'    => 90,
                'price'            => 1299.00,
                'discounted_price' => 999.00,
                'trial_days'       => 7,
                'features'         => json_encode([
                    'Everything in Monthly',
                    '3 Months Access',
                    'Priority Support',
                    'Advanced Progress Reports',
                ]),
                'is_active'        => 1,
                'sort_order'       => 2,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'name'             => 'Yearly Plan',
                'duration_days'    => 365,
                'price'            => 3999.00,
                'discounted_price' => 2999.00,
                'trial_days'       => 7,
                'features'         => json_encode([
                    'Everything in Quarterly',
                    '12 Months Access',
                    'Best Value — Save 40%',
                    'Exclusive Wellness Content',
                ]),
                'is_active'        => 1,
                'sort_order'       => 3,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }
}
