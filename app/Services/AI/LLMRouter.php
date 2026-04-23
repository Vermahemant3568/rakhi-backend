<?php

namespace App\Services\AI;

use App\Models\LlmConfig;
use Illuminate\Support\Facades\Log;

class LLMRouter
{
    public function __construct(
        private GeminiService       $gemini,
        private ChatGPTService      $chatgpt,
        private OpenRouterService   $openrouter,
    ) {}

    public function chat(string $prompt, array $history = []): string
    {
        $graceful = "I'm having a little trouble connecting right now \uD83C\uDF38 Give me a moment and try again \u2014 I'm here for you!";

        try {
            $config = $this->getActiveConfig();
        } catch (\Exception $e) {
            Log::error('LLM config unavailable: ' . $e->getMessage());
            return $graceful;
        }

        try {
            return match($config->provider) {
                'gemini'      => $this->gemini->chat($prompt, $history),
                'chatgpt'     => $this->chatgpt->chat($prompt, $history),
                'openrouter'  => $this->openrouter->chat($prompt, $history),
                default       => $this->gemini->chat($prompt, $history),
            };
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('LLM primary timed out (' . $config->provider . '), trying fallback: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('LLM primary failed (' . $config->provider . '): ' . $e->getMessage());
        }

        // Fallback chain: try each other provider in order
        $fallbacks = $this->getFallbackOrder($config->provider);

        foreach ($fallbacks as $provider) {
            try {
                Log::info('LLM trying fallback provider: ' . $provider);
                return match($provider) {
                    'gemini'     => $this->gemini->chat($prompt, $history),
                    'chatgpt'    => $this->chatgpt->chat($prompt, $history),
                    'openrouter' => $this->openrouter->chat($prompt, $history),
                };
            } catch (\Exception $e) {
                Log::error('LLM fallback (' . $provider . ') failed: ' . $e->getMessage());
            }
        }

        return $graceful;
    }

    private function getFallbackOrder(string $primary): array
    {
        $all = ['gemini', 'chatgpt', 'openrouter'];
        return array_values(array_filter($all, fn($p) => $p !== $primary));
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
        $config = cache()->remember('llm_active_config', 30, fn() =>
            LlmConfig::where('is_active', 1)->first()
        );

        if (!$config) {
            throw new \Exception('No active LLM configured. Please activate one from admin panel.');
        }

        return $config;
    }

    public static function clearCache(): void
    {
        cache()->forget('llm_active_config');
    }
}
