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
        foreach (array_slice($history, -20) as $msg) {
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

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            $payload
        );

        if ($response->successful()) {
            $text = $response->json('candidates.0.content.parts.0.text') ?? '';
            if (!empty($text)) {
                return $text;
            }
            // Successful HTTP but empty content — safety block or empty candidates
            $finishReason = $response->json('candidates.0.finishReason') ?? 'UNKNOWN';
            Log::warning('Gemini returned empty text. finishReason: ' . $finishReason . ' | body: ' . $response->body());
            throw new \Exception('Gemini returned empty response (finishReason: ' . $finishReason . ')');
        }

        Log::error('Gemini API failed: HTTP ' . $response->status() . ' | ' . $response->body());
        throw new \Exception('Gemini API error: HTTP ' . $response->status());
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

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';
        if (empty($text)) {
            Log::error('Gemini analyzeImage failed: HTTP ' . $response->status() . ' | ' . $response->body());
        }
        return $text;
    }

    public function embed(string $text): array
    {
        $config = $this->getConfig(); // throws if not configured
        $apiKey = $this->decryptKey($config->api_key);

        $response = Http::timeout(15)->post(
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
