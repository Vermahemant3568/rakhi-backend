<?php

namespace App\Services\AI;

use App\Models\LlmConfig;
use Illuminate\Support\Facades\Log;

class LLMRouter
{
    private GeminiService $gemini;
    private ChatGPTService $chatgpt;

    public function __construct(
        GeminiService $gemini,
        ChatGPTService $chatgpt
    ) {
        $this->gemini  = $gemini;
        $this->chatgpt = $chatgpt;
    }

    public function chat(string $prompt, array $history = []): string
    {
        $config = $this->getActiveConfig();

        try {
            return match($config->provider) {
                'gemini'  => $this->gemini->chat($prompt, $history),
                'chatgpt' => $this->chatgpt->chat($prompt, $history),
                default   => $this->gemini->chat($prompt, $history),
            };
        } catch (\Exception $e) {
            Log::error('LLM primary failed (' . $config->provider . '): ' . $e->getMessage());

            // Try the other provider as fallback before giving up
            try {
                if ($config->provider === 'gemini') {
                    return $this->chatgpt->chat($prompt, $history);
                }
                return $this->gemini->chat($prompt, $history);
            } catch (\Exception $fallbackEx) {
                Log::error('LLM fallback also failed: ' . $fallbackEx->getMessage());
                return $this->fallbackResponse();
            }
        }
    }

    public function analyzeImage(
        string $imageBase64,
        string $mimeType,
        string $prompt
    ): string {
        return $this->gemini->analyzeImage($imageBase64, $mimeType, $prompt);
    }

    public function getActiveConfig(): LlmConfig
    {
        $config = LlmConfig::where('is_active', 1)->first();

        if (!$config) {
            throw new \Exception('No active LLM configured. Please activate one from admin panel.');
        }

        return $config;
    }

    private function fallbackResponse(): string
    {
        return "I'm having a little trouble right now. " .
               "Please try again in a moment. I'm here for you! 💙";
    }
}
