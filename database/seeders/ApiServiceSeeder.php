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
                'service_name' => 'msg91',
                'display_name' => 'MSG91 OTP Service',
                'config'       => json_encode(['api_key' => '', 'template_id' => '']),
                'field_labels' => json_encode(['api_key' => 'MSG91 Auth Key', 'template_id' => 'OTP Template ID']),
                'is_active'    => 0,
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
            [
                'service_name' => 'voice_provider',
                'display_name' => 'TTS Provider Settings',
                'config'       => json_encode([
                    'provider' => 'google',
                ]),
                'field_labels' => json_encode([
                    'provider' => 'Active TTS Provider (google / elevenlabs)',
                ]),
                'is_active'    => 1,
            ],
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
            [
                'service_name' => 'elevenlabs_tts',
                'display_name' => 'ElevenLabs Text-to-Speech',
                'config'       => json_encode([
                    'api_key'          => '',
                    'voice_id'         => '21m00Tcm4TlvDq8ikWAM',
                    'model'            => 'eleven_turbo_v2_5',
                    'stability'        => '0.5',
                    'similarity_boost' => '0.75',
                    'style'            => '0.0',
                ]),
                'field_labels' => json_encode([
                    'api_key'          => 'ElevenLabs API Key',
                    'voice_id'         => 'Voice ID',
                    'model'            => 'Model (eleven_turbo_v2_5 recommended)',
                    'stability'        => 'Stability (0.0 - 1.0)',
                    'similarity_boost' => 'Similarity Boost (0.0 - 1.0)',
                    'style'            => 'Style (0.0 - 1.0)',
                ]),
                'is_active'    => 0,
            ],
        ]);
    }
}
