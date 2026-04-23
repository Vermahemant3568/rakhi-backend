<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LlmConfigSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('llm_configs')->insertOrIgnore([
            [
                'provider'    => 'gemini',
                'api_key'     => encrypt('your_gemini_api_key'),
                'model_name'  => 'gemini-2.0-flash',
                'is_active'   => 1,
                'max_tokens'  => 300,
                'temperature' => 0.65,
                'top_p'       => 0.85,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'provider'    => 'chatgpt',
                'api_key'     => encrypt('your_openai_api_key'),
                'model_name'  => 'gpt-4o-mini',
                'is_active'   => 0,
                'max_tokens'  => 300,
                'temperature' => 0.65,
                'top_p'       => 0.85,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'provider'    => 'openrouter',
                'api_key'     => encrypt('your_openrouter_api_key'),
                'model_name'  => 'google/gemini-2.5-flash-lite',
                'is_active'   => 0,
                'max_tokens'  => 300,
                'temperature' => 0.65,
                'top_p'       => 0.85,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }
}
