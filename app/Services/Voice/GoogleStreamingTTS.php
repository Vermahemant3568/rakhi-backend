<?php

namespace App\Services\Voice;

use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;

class GoogleStreamingTTS
{
    protected TextToSpeechClient $client;

    public function __construct()
    {
        $this->client = new TextToSpeechClient();
    }

    /**
     * Convert text to raw PCM audio
     */
    public function synthesize(string $text): string
    {
        $input = new SynthesisInput([
            'text' => $text
        ]);

        $voice = new VoiceSelectionParams([
            'language_code' => 'en-IN',
            'name' => 'en-IN-Wavenet-D', // natural female voice
        ]);

        $audioConfig = new AudioConfig([
            'audio_encoding' => AudioEncoding::LINEAR16,
            'sample_rate_hertz' => 16000,
        ]);

        $response = $this->client->synthesizeSpeech(
            $input,
            $voice,
            $audioConfig
        );

        return $response->getAudioContent(); // raw PCM bytes
    }

    public function close()
    {
        $this->client->close();
    }
}