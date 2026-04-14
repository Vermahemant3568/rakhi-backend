<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApiServiceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('api_services')->insertOrIgnore([
            [
                'service_name' => 'google_stt',
                'display_name' => 'Google Speech-to-Text',
                'config'       => json_encode(['api_key' => 'your_google_api_key']),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'google_tts',
                'display_name' => 'Google Text-to-Speech',
                'config'       => json_encode(['api_key' => 'your_google_api_key']),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'pinecone',
                'display_name' => 'Pinecone Vector DB',
                'config'       => json_encode([
                    'api_key' => 'your_pinecone_api_key',
                    'host'    => 'https://rakhi-ai-vsu28xc.svc.aped-4627-b74a.pinecone.io',
                    'index'   => 'rakhi-ai',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'razorpay',
                'display_name' => 'Razorpay Payment Gateway',
                'config'       => json_encode([
                    'key_id'     => 'your_razorpay_key_id',
                    'key_secret' => 'your_razorpay_key_secret',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'firebase',
                'display_name' => 'Firebase Push Notifications',
                'config'       => json_encode([
                    'server_key' => 'your_firebase_server_key',
                    'project_id' => 'your_firebase_project_id',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'msg91',
                'display_name' => 'MSG91 OTP Service',
                'config'       => json_encode([
                    'api_key'     => 'your_msg91_api_key',
                    'template_id' => 'your_template_id',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'pusher',
                'display_name' => 'Pusher Real-time',
                'config'       => json_encode([
                    'app_id'     => 'your_pusher_app_id',
                    'app_key'    => 'your_pusher_app_key',
                    'app_secret' => 'your_pusher_app_secret',
                    'cluster'    => 'ap2',
                ]),
                'is_active'    => 1,
            ],
        ]);
    }
}
