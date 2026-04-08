<?php

namespace App\Services\AI;

use App\Models\LlmConfig;

class EmbeddingService
{
    public function __construct(
        private GeminiService $gemini,
        private ChatGPTService $chatgpt
    ) {}

    public function embed(string $text): array
    {
        $config = LlmConfig::where('is_active', 1)->first();

        if (!$config) {
            return $this->gemini->embed($text);
        }

        return match($config->provider) {
            'chatgpt' => $this->chatgpt->embed($text),
            default   => $this->gemini->embed($text),
        };
    }
}
