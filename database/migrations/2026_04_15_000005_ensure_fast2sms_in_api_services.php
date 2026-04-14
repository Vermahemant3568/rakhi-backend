<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add field_labels column if missing
        if (!Schema::hasColumn('api_services', 'field_labels')) {
            Schema::table('api_services', function (Blueprint $table) {
                $table->json('field_labels')->nullable()->after('config');
            });
        }

        // 2. Guarantee fast2sms exists — insert or update
        DB::table('api_services')->updateOrInsert(
            ['service_name' => 'fast2sms'],
            [
                'display_name' => 'Fast2SMS OTP Service',
                'is_active'    => 1,
                'config'       => json_encode(['api_key' => '']),
                'field_labels' => json_encode(['api_key' => 'Fast2SMS API Key']),
            ]
        );

        // 3. Update field_labels for all other existing services
        $allLabels = [
            'google_stt' => ['api_key' => 'Google API Key'],
            'google_tts' => ['api_key' => 'Google API Key'],
            'pinecone'   => ['api_key' => 'Pinecone API Key', 'host' => 'Host URL', 'index' => 'Index Name'],
            'razorpay'   => ['key_id' => 'Razorpay Key ID', 'key_secret' => 'Razorpay Key Secret'],
            'firebase'   => ['server_key' => 'Firebase Server Key', 'project_id' => 'Project ID'],
            'pusher'     => ['app_id' => 'App ID', 'app_key' => 'App Key', 'app_secret' => 'App Secret', 'cluster' => 'Cluster'],
        ];

        foreach ($allLabels as $serviceName => $labels) {
            DB::table('api_services')
                ->where('service_name', $serviceName)
                ->update(['field_labels' => json_encode($labels)]);
        }
    }

    public function down(): void
    {
        DB::table('api_services')->where('service_name', 'fast2sms')->delete();
    }
};
