<?php

namespace App\Services\Voice;

class SpeechToTextService
{
    public function transcribe(string $audioStream): string
    {
        // Google STT integration will go here
        return "converted speech text";
    }
}