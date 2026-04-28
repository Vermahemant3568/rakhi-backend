<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_services')->insertOrIgnore([
            [
                'service_name' => 'groq_stt',
                'display_name' => 'Groq Speech-to-Text',
                'config'       => json_encode([
                    'api_key'    => '',
                    'model_name' => 'whisper-large-v3-turbo',
                ]),
                'field_labels' => json_encode([
                    'api_key'    => 'Groq API Key',
                    'model_name' => 'Model (whisper-large-v3-turbo recommended)',
                ]),
                'is_active'    => 0,
            ],
            [
                'service_name' => 'stt_provider',
                'display_name' => 'STT Provider Settings',
                'config'       => json_encode([
                    'provider' => 'google',
                ]),
                'field_labels' => json_encode([
                    'provider' => 'Active STT Provider (google / groq)',
                ]),
                'is_active'    => 1,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('api_services')
            ->whereIn('service_name', ['groq_stt', 'stt_provider'])
            ->delete();
    }
};
