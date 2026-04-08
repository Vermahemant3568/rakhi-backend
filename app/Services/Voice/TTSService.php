<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TTSService
{
    private string $apiKey;

    private array $voiceMap = [
        'en-IN' => ['name' => 'en-IN-Neural2-A', 'gender' => 'FEMALE'],
        'hi-IN' => ['name' => 'hi-IN-Neural2-A', 'gender' => 'FEMALE'],
        'ta-IN' => ['name' => 'ta-IN-Neural2-A', 'gender' => 'FEMALE'],
        'te-IN' => ['name' => 'te-IN-Neural2-A', 'gender' => 'FEMALE'],
        'bn-IN' => ['name' => 'bn-IN-Wavenet-A', 'gender' => 'FEMALE'],
        'mr-IN' => ['name' => 'mr-IN-Wavenet-A', 'gender' => 'FEMALE'],
    ];

    public function __construct()
    {
        $this->apiKey = config('services.google.api_key');
    }

    public function synthesize(string $text, string $languageCode = 'en-IN'): string
    {
        try {
            $voice = $this->voiceMap[$languageCode] ?? $this->voiceMap['en-IN'];

            $response = Http::timeout(30)->post(
                "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->apiKey}",
                [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => $languageCode,
                        'name'         => $voice['name'],
                        'ssmlGender'   => $voice['gender'],
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                        'speakingRate'  => 1.0,
                        'pitch'         => 0.0,
                    ],
                ]
            );

            if ($response->failed()) {
                Log::error('TTS failed: ' . $response->body());
                return '';
            }

            $audioContent = $response->json('audioContent');
            $fileName     = 'voice/' . uniqid() . '.mp3';

            Storage::disk('public')->put($fileName, base64_decode($audioContent));

            return Storage::disk('public')->url($fileName);

        } catch (\Exception $e) {
            Log::error('TTS Exception: ' . $e->getMessage());
            return '';
        }
    }
}
