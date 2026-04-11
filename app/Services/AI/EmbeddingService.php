<?php

namespace App\Services\AI;

use App\Models\LlmConfig;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function __construct(
        private GeminiService $gemini,
        private ChatGPTService $chatgpt
    ) {}

    public function embed(string $text): array
    {
        if (empty(trim($text))) return [];

        try {
            $config = LlmConfig::where('is_active', 1)->first();

            if (!$config) {
                return $this->gemini->embed($text);
            }

            return match($config->provider) {
                'chatgpt' => $this->chatgpt->embed($text),
                default   => $this->gemini->embed($text),
            };
        } catch (\Exception $e) {
            // Embedding is non-critical — quota errors, network issues etc.
            // Return empty so callers skip vector operations gracefully
            Log::warning('EmbeddingService failed (non-fatal): ' . $e->getMessage());
            return [];
        }
    }
}
