<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ApiConfigService;

class ElevenLabsTTSService
{
    // Low-latency model — best for real-time voice calls
    private const DEFAULT_MODEL = 'eleven_turbo_v2_5';

    // Default voice: "Rachel" — warm, natural Indian-English female voice
    private const DEFAULT_VOICE_ID = '21m00Tcm4TlvDq8ikWAM';

    private const API_BASE = 'https://api.elevenlabs.io/v1';

    private function config(): array
    {
        return ApiConfigService::all('elevenlabs_tts');
    }

    private function apiKey(): string
    {
        return $this->config()['api_key'] ?? '';
    }

    public function synthesize(string $text, string $languageCode = 'en-IN'): string
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            Log::warning('ElevenLabs TTS: api_key not configured');
            return '';
        }

        $config  = $this->config();
        $voiceId = $config['voice_id'] ?? self::DEFAULT_VOICE_ID;
        $model   = $config['model']    ?? self::DEFAULT_MODEL;

        // Voice settings — tunable from admin panel
        $stability       = (float) ($config['stability']        ?? 0.5);
        $similarityBoost = (float) ($config['similarity_boost'] ?? 0.75);
        $style           = (float) ($config['style']            ?? 0.0);

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'xi-api-key'   => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'audio/mpeg',
                ])
                ->post(self::API_BASE . "/text-to-speech/{$voiceId}", [
                    'text'     => $this->prepareText($text),
                    'model_id' => $model,
                    'voice_settings' => [
                        'stability'        => $stability,
                        'similarity_boost' => $similarityBoost,
                        'style'            => $style,
                        'use_speaker_boost'=> true,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('ElevenLabs TTS failed: HTTP ' . $response->status() . ' — ' . $response->body());
                return '';
            }

            $audioContent = $response->body();
            if (empty($audioContent)) return '';

            // Return base64 directly — no disk write, no file storage
            return base64_encode($audioContent);

        } catch (\Exception $e) {
            Log::error('ElevenLabs TTS Exception: ' . $e->getMessage());
            return '';
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey());
    }

    // Strip markdown/emojis — same as Google TTS prep
    private function prepareText(string $text): string
    {
        $text = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $text);
        $text = preg_replace('/#{1,6}\s/', '', $text);
        $text = preg_replace('/[\x{1F300}-\x{1FFFF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim(substr($text, 0, 300));
    }
}
