<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_services')
            ->where('service_name', 'voice_provider')
            ->update(['display_name' => 'TTS Provider Settings']);
    }

    public function down(): void
    {
        DB::table('api_services')
            ->where('service_name', 'voice_provider')
            ->update(['display_name' => 'Voice Provider Settings']);
    }
};
