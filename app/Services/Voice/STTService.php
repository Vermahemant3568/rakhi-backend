<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class STTService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.api_key');
    }

    public function transcribe(
        string $audioBase64,
        string $mimeType,
        string $languageCode = 'en-IN'
    ): string {
        try {
            $encoding = $this->getEncoding($mimeType);

            $response = Http::timeout(30)->post(
                "https://speech.googleapis.com/v1/speech:recognize?key={$this->apiKey}",
                [
                    'config' => [
                        'encoding'                  => $encoding,
                        'sampleRateHertz'           => 16000,
                        'languageCode'              => $languageCode,
                        'model'                     => 'latest_long',
                        'alternativeLanguageCodes'  => ['hi-IN', 'en-IN'],
                        'enableAutomaticPunctuation'=> true,
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

            return $response->json('results.0.alternatives.0.transcript') ?? '';

        } catch (\Exception $e) {
            Log::error('STT Exception: ' . $e->getMessage());
            return '';
        }
    }

    private function getEncoding(string $mimeType): string
    {
        return match($mimeType) {
            'audio/webm' => 'WEBM_OPUS',
            'audio/ogg'  => 'OGG_OPUS',
            'audio/wav'  => 'LINEAR16',
            'audio/mp3'  => 'MP3',
            'audio/flac' => 'FLAC',
            default      => 'WEBM_OPUS',
        };
    }
}
