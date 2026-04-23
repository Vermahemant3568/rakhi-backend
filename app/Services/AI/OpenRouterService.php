<?php

namespace App\Services\AI;

use App\Models\LlmConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    private const BASE_URL = 'https://openrouter.ai/api/v1/chat/completions';

    private function decryptKey(string $key): string
    {
        try {
            return decrypt($key);
        } catch (\Exception) {
            return $key;
        }
    }

    private function getConfig(): LlmConfig
    {
        $config = LlmConfig::where('provider', 'openrouter')
                           ->where('is_active', 1)
                           ->first();

        if (!$config) {
            throw new \Exception('OpenRouter LLM config not found. Please activate it from admin panel.');
        }

        return $config;
    }

    public function chat(string $prompt, array $history = []): string
    {
        $config = $this->getConfig();
        $apiKey = $this->decryptKey($config->api_key);
        // Default to a free/cheap model — admin can override via model_name
        $model  = $config->model_name ?? 'google/gemini-2.0-flash-exp:free';

        $messages = [];

        foreach (array_slice($history, -20) as $msg) {
            $messages[] = [
                'role'    => $msg['role'] === 'rakhi' ? 'assistant' : 'user',
                'content' => $msg['message'],
            ];
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $prompt,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => config('app.url', 'https://rakhi.ai'),
            'X-Title'       => 'Rakhi Health Coach',
        ])->timeout(30)->post(self::BASE_URL, [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $config->max_tokens ?? 300,
            'temperature' => (float) ($config->temperature ?? 0.65),
            'top_p'       => (float) ($config->top_p ?? 0.85),
        ]);

        if ($response->failed()) {
            Log::error('OpenRouter API failed: HTTP ' . $response->status() . ' | ' . $response->body());
            throw new \Exception('OpenRouter API error: HTTP ' . $response->status());
        }

        $text = $response->json('choices.0.message.content') ?? '';

        if (empty($text)) {
            Log::warning('OpenRouter returned empty content. Body: ' . $response->body());
            throw new \Exception('OpenRouter returned empty response');
        }

        return $text;
    }
}
