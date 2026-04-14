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
                'config'       => json_encode(['api_key' => '']),
                'field_labels' => json_encode(['api_key' => 'Google API Key']),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'google_tts',
                'display_name' => 'Google Text-to-Speech',
                'config'       => json_encode(['api_key' => '']),
                'field_labels' => json_encode(['api_key' => 'Google API Key']),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'pinecone',
                'display_name' => 'Pinecone Vector DB',
                'config'       => json_encode([
                    'api_key' => '',
                    'host'    => 'https://rakhi-ai-vsu28xc.svc.aped-4627-b74a.pinecone.io',
                    'index'   => 'rakhi-ai',
                ]),
                'field_labels' => json_encode([
                    'api_key' => 'Pinecone API Key',
                    'host'    => 'Host URL',
                    'index'   => 'Index Name',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'razorpay',
                'display_name' => 'Razorpay Payment Gateway',
                'config'       => json_encode([
                    'key_id'     => '',
                    'key_secret' => '',
                ]),
                'field_labels' => json_encode([
                    'key_id'     => 'Razorpay Key ID',
                    'key_secret' => 'Razorpay Key Secret',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'firebase',
                'display_name' => 'Firebase Push Notifications',
                'config'       => json_encode([
                    'server_key' => '',
                    'project_id' => '',
                ]),
                'field_labels' => json_encode([
                    'server_key' => 'Firebase Server Key',
                    'project_id' => 'Project ID',
                ]),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'fast2sms',
                'display_name' => 'Fast2SMS OTP Service',
                'config'       => json_encode(['api_key' => '']),
                'field_labels' => json_encode(['api_key' => 'Fast2SMS API Key']),
                'is_active'    => 1,
            ],
            [
                'service_name' => 'pusher',
                'display_name' => 'Pusher Real-time',
                'config'       => json_encode([
                    'app_id'     => '',
                    'app_key'    => '',
                    'app_secret' => '',
                    'cluster'    => 'ap2',
                ]),
                'field_labels' => json_encode([
                    'app_id'     => 'App ID',
                    'app_key'    => 'App Key',
                    'app_secret' => 'App Secret',
                    'cluster'    => 'Cluster',
                ]),
                'is_active'    => 1,
            ],
        ]);
    }
}
