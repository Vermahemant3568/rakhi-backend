<?php

namespace App\Services\Safety;

class FallbackResponses
{
    public static function sttFail(): string
    {
        return "I didn't catch that clearly. Could you please repeat it?";
    }

    public static function aiFail(): string
    {
        return "I'm here with you. Let's pause for a moment and continue.";
    }
}