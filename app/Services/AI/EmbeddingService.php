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
            // Always use Gemini for embeddings — consistent 768-dim vectors.
            // Switching providers mid-way causes dimension mismatch in Pinecone.
            return $this->gemini->embed($text);
        } catch (\Exception $e) {
            try {
                return $this->chatgpt->embed($text);
            } catch (\Exception $fallback) {
                Log::warning('EmbeddingService failed (non-fatal): ' . $fallback->getMessage());
                return [];
            }
        }
    }
}
