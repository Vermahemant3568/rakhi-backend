<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['name' => 'English',    'code' => 'en',    'native_name' => 'English',    'tts_code' => 'en-IN', 'stt_code' => 'en-IN', 'sort_order' => 1],
            ['name' => 'Hindi',      'code' => 'hi',    'native_name' => 'हिन्दी',      'tts_code' => 'hi-IN', 'stt_code' => 'hi-IN', 'sort_order' => 2],
            ['name' => 'Tamil',      'code' => 'ta',    'native_name' => 'தமிழ்',       'tts_code' => 'ta-IN', 'stt_code' => 'ta-IN', 'sort_order' => 3],
            ['name' => 'Telugu',     'code' => 'te',    'native_name' => 'తెలుగు',      'tts_code' => 'te-IN', 'stt_code' => 'te-IN', 'sort_order' => 4],
            ['name' => 'Kannada',    'code' => 'kn',    'native_name' => 'ಕನ್ನಡ',       'tts_code' => 'kn-IN', 'stt_code' => 'kn-IN', 'sort_order' => 5],
            ['name' => 'Malayalam',  'code' => 'ml',    'native_name' => 'മലയാളം',      'tts_code' => 'ml-IN', 'stt_code' => 'ml-IN', 'sort_order' => 6],
            ['name' => 'Bengali',    'code' => 'bn',    'native_name' => 'বাংলা',        'tts_code' => 'bn-IN', 'stt_code' => 'bn-IN', 'sort_order' => 7],
            ['name' => 'Marathi',    'code' => 'mr',    'native_name' => 'मराठी',        'tts_code' => 'mr-IN', 'stt_code' => 'mr-IN', 'sort_order' => 8],
        ];

        foreach ($languages as $language) {
            DB::table('languages')->insertOrIgnore(array_merge($language, [
                'is_active'  => 1,
                'created_at' => now(),
            ]));
        }
    }
}
