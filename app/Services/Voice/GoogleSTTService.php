<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ApiConfigService;

class GoogleSTTService
{
    // Minimum base64 length to be worth sending (~0.5s of audio)
    private const MIN_AUDIO_LENGTH = 1000;

    private function apiKey(): string
    {
        return ApiConfigService::get('google_stt', 'api_key', config('services.google.api_key', '')) ?? '';
    }

    public function transcribe(
        string $audioBase64,
        string $mimeType,
        string $languageCode = 'en-IN'
    ): string {
        // Reject silent/empty audio before hitting Google API
        if (strlen($audioBase64) < self::MIN_AUDIO_LENGTH) {
            return '';
        }

        $key = $this->apiKey();

        // No valid key configured — return empty so voice loop continues gracefully
        if (empty($key) || $key === 'your_google_api_key') {
            Log::warning('STT: Google API key not configured. Please set it in Admin → API Manager → Google STT.');
            return '';
        }

        try {
            $encoding = $this->getEncoding($mimeType);

            $response = Http::timeout(10)->post(
                "https://speech.googleapis.com/v1/speech:recognize?key={$key}",
                [
                    'config' => [
                        'encoding'                   => $encoding,
                        'sampleRateHertz'            => 16000,
                        'languageCode'               => $languageCode,
                        // latest_short is optimized for <60s conversational audio
                        'model'                      => 'latest_short',
                        'alternativeLanguageCodes'   => ['hi-IN', 'en-IN'],
                        'enableAutomaticPunctuation' => true,
                        // Improve accuracy for conversational speech
                        'useEnhanced'                => true,
                        'speechContexts'             => [[
                            'phrases' => ['Rakhi', 'health', 'diet', 'sugar', 'diabetes', 'PCOS', 'thyroid'],
                            'boost'   => 10,
                        ]],
                    ],
                    'audio' => [
                        'content' => $audioBase64,
                    ],
                ]
            );

            if ($response->failed()) {
                Log::error('STT failed: ' . $response->body());
                return '';
            }

            $transcript = $response->json('results.0.alternatives.0.transcript') ?? '';

            // Reject transcripts that are just noise/filler
            return $this->isValidTranscript($transcript) ? trim($transcript) : '';

        } catch (\Exception $e) {
            Log::error('STT Exception: ' . $e->getMessage());
            return '';
        }
    }

    // Reject single-character or pure punctuation transcripts
    private function isValidTranscript(string $text): bool
    {
        $clean = trim($text);
        if (strlen($clean) < 2) return false;
        if (preg_match('/^[\s\.\,\!\?\-]+$/', $clean)) return false;
        return true;
    }

    private function getEncoding(string $mimeType): string
    {
        return match(true) {
            str_contains($mimeType, 'webm')                          => 'WEBM_OPUS',
            str_contains($mimeType, 'ogg')                           => 'OGG_OPUS',
            str_contains($mimeType, 'wav'), str_contains($mimeType, 'wave') => 'LINEAR16',
            str_contains($mimeType, 'mp3'), str_contains($mimeType, 'mpeg') => 'MP3',
            str_contains($mimeType, 'flac')                          => 'FLAC',
            // m4a / mp4 / aac — all AAC-LC containers from mobile recorders
            str_contains($mimeType, 'm4a'),
            str_contains($mimeType, 'mp4'),
            str_contains($mimeType, 'aac')                           => 'MP4',
            default                                                  => 'WEBM_OPUS',
        };
    }
}
