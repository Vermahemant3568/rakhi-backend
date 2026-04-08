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
                'model_name'  => 'gemini-1.5-flash',
                'is_active'   => 1,
                'max_tokens'  => 1000,
                'temperature' => 0.70,
                'top_p'       => 0.95,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'provider'    => 'chatgpt',
                'api_key'     => encrypt('your_openai_api_key'),
                'model_name'  => 'gpt-4o',
                'is_active'   => 0,
                'max_tokens'  => 1000,
                'temperature' => 0.70,
                'top_p'       => 0.95,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }
}
