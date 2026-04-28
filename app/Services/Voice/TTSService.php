<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ApiConfigService;

class TTSService
{
    private const MAX_TTS_CHARS = 300;

    private function apiKey(): string
    {
        return ApiConfigService::get('google_tts', 'api_key', config('services.google.api_key'));
    }

    private array $voiceMap = [
        'en-IN' => ['name' => 'en-IN-Neural2-A', 'gender' => 'FEMALE'],
        'hi-IN' => ['name' => 'hi-IN-Neural2-A', 'gender' => 'FEMALE'],
        'ta-IN' => ['name' => 'ta-IN-Neural2-A', 'gender' => 'FEMALE'],
        'te-IN' => ['name' => 'te-IN-Neural2-A', 'gender' => 'FEMALE'],
        'bn-IN' => ['name' => 'bn-IN-Wavenet-A', 'gender' => 'FEMALE'],
        'mr-IN' => ['name' => 'mr-IN-Wavenet-A', 'gender' => 'FEMALE'],
    ];

    public function synthesize(string $text, string $languageCode = 'en-IN'): string
    {
        $text = $this->prepareForVoice($text);

        if (empty($text)) return '';

        try {
            $voice = $this->voiceMap[$languageCode] ?? $this->voiceMap['en-IN'];

            $response = Http::timeout(12)->post(
                "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->apiKey()}",
                [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => $languageCode,
                        'name'         => $voice['name'],
                        'ssmlGender'   => $voice['gender'],
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                        // 0.95 = slightly slower than normal — feels more human on a phone call
                        'speakingRate'  => 0.95,
                        'pitch'         => 0.0,
                        'effectsProfileId' => ['handset-class-device'],
                    ],
                ]
            );

            if ($response->failed()) {
                Log::error('TTS failed: ' . $response->body());
                return '';
            }

            // Return base64 directly — no disk write, no file storage
            return $response->json('audioContent') ?? '';

        } catch (\Exception $e) {
            Log::error('TTS Exception: ' . $e->getMessage());
            return '';
        }
    }

    // Strip markdown, emojis, truncate to max chars for fast TTS
    private function prepareForVoice(string $text): string
    {
        // Remove markdown formatting
        $text = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $text);
        $text = preg_replace('/_{1,2}([^_]+)_{1,2}/', '$1', $text);
        $text = preg_replace('/#{1,6}\s/', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);

        // Remove emojis — TTS reads them as "emoji" or skips awkwardly
        $text = preg_replace('/[\x{1F300}-\x{1FFFF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);

        // Collapse multiple spaces/newlines
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Truncate at sentence boundary within MAX_TTS_CHARS
        if (strlen($text) > self::MAX_TTS_CHARS) {
            $cut = substr($text, 0, self::MAX_TTS_CHARS);
            $lastPeriod = max(
                strrpos($cut, '. '),
                strrpos($cut, '? '),
                strrpos($cut, '! ')
            );
            $text = $lastPeriod > 50
                ? substr($text, 0, $lastPeriod + 1)
                : $cut;
        }

        return trim($text);
    }

}
