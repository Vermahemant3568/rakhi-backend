<?php

namespace App\Services\Safety;

class CallTerminationService
{
    protected array $endCallKeywords = [
        'stop',
        'end call',
        'hang up',
        'goodbye',
        'bye'
    ];

    public function shouldEndCall(string $text): bool
    {
        $text = strtolower(trim($text));

        foreach ($this->endCallKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function endCallMessage(): string
    {
        return "Thank you for talking with me. Take care of yourself. Goodbye!";
    }
}