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

        // Try Gemini first (768-dim), fall back to ChatGPT
        // If neither is active/configured, return empty silently
        try {
            $geminiActive = LlmConfig::where('provider', 'gemini')->where('is_active', 1)->exists();
            if ($geminiActive) {
                return $this->gemini->embed($text);
            }
        } catch (\Exception $e) {
            Log::warning('EmbeddingService Gemini failed: ' . $e->getMessage());
        }

        try {
            $chatgptActive = LlmConfig::where('provider', 'chatgpt')->where('is_active', 1)->exists();
            if ($chatgptActive) {
                return $this->chatgpt->embed($text);
            }
        } catch (\Exception $e) {
            Log::warning('EmbeddingService ChatGPT failed: ' . $e->getMessage());
        }

        // No embedding provider active — skip silently
        return [];
    }
}
