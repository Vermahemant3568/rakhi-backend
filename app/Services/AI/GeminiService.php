<?php

namespace App\Services\AI;

use App\Models\LlmConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
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
        $config = LlmConfig::where('provider', 'gemini')
                           ->where('is_active', 1)
                           ->first();

        if (!$config) {
            throw new \Exception('Gemini LLM config not found. Please activate it from admin panel.');
        }

        return $config;
    }

    public function chat(string $prompt, array $history = []): string
    {
        $config = $this->getConfig();
        $apiKey = $this->decryptKey($config->api_key);
        $model  = $config->model_name ?? 'gemini-2.0-flash-lite';

        $contents = [];
        foreach (array_slice($history, -6) as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'rakhi' ? 'model' : 'user',
                'parts' => [['text' => $msg['message']]]
            ];
        }
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => (float) ($config->temperature ?? 0.65),
                'topP'            => (float) ($config->top_p ?? 0.85),
                'maxOutputTokens' => $config->max_tokens ?? 220,
            ],
        ];

        // Retry up to 2 times with backoff for 429/503
        $attempts = 2;
        $delay    = 3;

        for ($i = 0; $i < $attempts; $i++) {
            $response = Http::timeout(12)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text') ?? '';
                if (!empty($text)) {
                    return $text;
                }
            }

            $status = $response->status();

            if (in_array($status, [429, 503]) && $i < $attempts - 1) {
                Log::warning("Gemini {$status} on attempt " . ($i + 1) . ", retrying in {$delay}s...");
                sleep($delay);
                continue;
            }

            Log::error('Gemini API failed: ' . $response->body());
            throw new \Exception('Gemini API error: HTTP ' . $status);
        }

        throw new \Exception('Gemini API error: all retries exhausted');
    }

    public function analyzeImage(
        string $imageBase64,
        string $mimeType,
        string $prompt
    ): string {
        $config = $this->getConfig(); // throws if not configured
        $apiKey = $this->decryptKey($config->api_key);

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key={$apiKey}",
            [
                'contents' => [[
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data'      => $imageBase64,
                            ]
                        ],
                        ['text' => $prompt]
                    ]
                ]]
            ]
        );

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }

    public function embed(string $text): array
    {
        $config = $this->getConfig(); // throws if not configured
        $apiKey = $this->decryptKey($config->api_key);

        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}",
            [
                'model'   => 'models/gemini-embedding-001',
                'content' => ['parts' => [['text' => $text]]],
                'outputDimensionality' => 768,
            ]
        );

        return $response->json('embedding.values') ?? [];
    }
}
