<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiModel;

class FixGeminiModelSeeder extends Seeder
{
    public function run()
    {
        // Update existing gemini model or create new one
        AiModel::updateOrCreate(
            ['provider' => 'gemini', 'is_active' => true],
            ['model_name' => 'gemini-2.5-flash']
        );
        
        echo "Gemini model updated to gemini-2.5-flash\n";
    }
}