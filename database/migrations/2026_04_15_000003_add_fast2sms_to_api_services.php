<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove old msg91 record — no longer used
        DB::table('api_services')->where('service_name', 'msg91')->delete();

        // Insert fast2sms if not already present
        $exists = DB::table('api_services')
            ->where('service_name', 'fast2sms')
            ->exists();

        if (!$exists) {
            DB::table('api_services')->insert([
                'service_name' => 'fast2sms',
                'display_name' => 'Fast2SMS OTP Service',
                'config'       => json_encode(['api_key' => '']),
                'is_active'    => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('api_services')->where('service_name', 'fast2sms')->delete();
    }
};
