<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_services')->updateOrInsert(
            ['service_name' => 'otp_mode'],
            [
                'display_name' => 'OTP Mode',
                'is_active'    => 1,
                'config'       => json_encode(['mode' => 'TEST']),
                'field_labels' => json_encode(['mode' => 'OTP Mode (LIVE / TEST)']),
            ]
        );
    }

    public function down(): void
    {
        DB::table('api_services')->where('service_name', 'otp_mode')->delete();
    }
};
