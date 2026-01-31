<?php

namespace App\Services\Voice;

class TextToSpeechService
{
    public function speak(string $text): string
    {
        // Google TTS integration will go here
        return "audio_stream_url_or_bytes";
    }
}