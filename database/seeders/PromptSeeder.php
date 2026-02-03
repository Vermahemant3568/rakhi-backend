<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PromptTemplate;
use App\Models\RakhiRule;

class PromptSeeder extends Seeder
{
    public function run()
    {
        // Create default Rakhi rules
        $rules = [
            ['key' => 'ai_enabled', 'value' => true, 'is_active' => true],
            ['key' => 'personality', 'value' => 'friendly and helpful health coach', 'is_active' => true],
            ['key' => 'tone', 'value' => 'warm and encouraging', 'is_active' => true],
            ['key' => 'response_style', 'value' => 'concise and supportive', 'is_active' => true],
        ];

        foreach ($rules as $rule) {
            RakhiRule::updateOrCreate(
                ['key' => $rule['key']],
                $rule
            );
        }

        // Create default prompt templates
        $templates = [
            [
                'type' => 'chat',
                'template' => 'You are {app_name}, a {personality} with a {tone} tone. Keep responses brief and {response_style}.

User: {user_message}

Respond naturally:',
                'is_active' => true
            ],
            [
                'type' => 'voice',
                'template' => 'You are {app_name} in voice chat. Be {personality}. Keep responses very short.

User: {user_message}

Brief response:',
                'is_active' => false
            ]
        ];

        foreach ($templates as $template) {
            PromptTemplate::updateOrCreate(
                ['type' => $template['type']],
                $template
            );
        }

        echo "Prompt templates and rules seeded successfully!\n";
    }
}