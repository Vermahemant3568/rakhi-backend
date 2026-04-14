<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add field_labels column if it doesn't exist
        if (!Schema::hasColumn('api_services', 'field_labels')) {
            Schema::table('api_services', function (Blueprint $table) {
                $table->json('field_labels')->nullable()->after('config');
            });
        }

        // Update existing records with field_labels
        $labels = [
            'fast2sms'   => ['api_key' => 'Fast2SMS API Key'],
            'msg91'      => ['api_key' => 'MSG91 Auth Key', 'template_id' => 'Template ID'],
            'google_stt' => ['api_key' => 'Google API Key'],
            'google_tts' => ['api_key' => 'Google API Key'],
            'pinecone'   => ['api_key' => 'Pinecone API Key', 'host' => 'Host URL', 'index' => 'Index Name'],
            'razorpay'   => ['key_id' => 'Razorpay Key ID', 'key_secret' => 'Razorpay Key Secret'],
            'firebase'   => ['server_key' => 'Firebase Server Key', 'project_id' => 'Project ID'],
            'pusher'     => ['app_id' => 'App ID', 'app_key' => 'App Key', 'app_secret' => 'App Secret', 'cluster' => 'Cluster'],
        ];

        foreach ($labels as $serviceName => $fieldLabels) {
            DB::table('api_services')
                ->where('service_name', $serviceName)
                ->update(['field_labels' => json_encode($fieldLabels)]);
        }

        // Insert fast2sms if not exists
        $exists = DB::table('api_services')->where('service_name', 'fast2sms')->exists();
        if (!$exists) {
            DB::table('api_services')->insert([
                'service_name' => 'fast2sms',
                'display_name' => 'Fast2SMS OTP Service',
                'is_active'    => 1,
                'config'       => json_encode(['api_key' => '']),
                'field_labels' => json_encode(['api_key' => 'Fast2SMS API Key']),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('api_services', function (Blueprint $table) {
            $table->dropColumn('field_labels');
        });
    }
};
