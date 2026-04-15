<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_services')->updateOrInsert(
            ['service_name' => 'msg91'],
            [
                'display_name' => 'MSG91 OTP Service',
                'is_active'    => 0,
                'config'       => json_encode(['api_key' => '', 'template_id' => '']),
                'field_labels' => json_encode(['api_key' => 'MSG91 Auth Key', 'template_id' => 'OTP Template ID']),
            ]
        );
    }

    public function down(): void
    {
        DB::table('api_services')->where('service_name', 'msg91')->delete();
    }
};
