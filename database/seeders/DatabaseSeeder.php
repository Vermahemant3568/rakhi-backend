<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Admin account
            AdminSeeder::class,

            // 2. Core lookup data
            LanguageSeeder::class,
            GoalSeeder::class,

            // 3. Coaches (must come before prompt templates)
            CoachSeeder::class,

            // 4. AI config
            LlmConfigSeeder::class,

            // 5. Prompt templates (depends on coaches)
            PromptTemplateSeeder::class,

            // 6. Rakhi rules
            RakhiRuleSeeder::class,

            // 7. Subscription plans
            SubscriptionPlanSeeder::class,

            // 8. External API services
            ApiServiceSeeder::class,

            // 9. Knowledge base
            KnowledgeBaseSeeder::class,
        ]);
    }
}
