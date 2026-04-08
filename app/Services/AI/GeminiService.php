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
        return LlmConfig::where('provider', 'gemini')
                        ->where('is_active', 1)
                        ->first();
    }

    public function chat(string $prompt, array $history = []): string
    {
        $config = $this->getConfig();
        $apiKey = $this->decryptKey($config->api_key);
        $model  = $config->model_name ?? 'gemini-2.0-flash';

        $contents = [];

        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'rakhi' ? 'model' : 'user',
                'parts' => [['text' => $msg['message']]]
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents'         => $contents,
                'generationConfig' => [
                    'temperature'     => (float) $config->temperature,
                    'topP'            => (float) $config->top_p,
                    'maxOutputTokens' => $config->max_tokens,
                ],
            ]
        );

        if ($response->failed()) {
            Log::error('Gemini API failed: ' . $response->body());
            throw new \Exception('Gemini API error');
        }

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }

    public function analyzeImage(
        string $imageBase64,
        string $mimeType,
        string $prompt
    ): string {
        $config = $this->getConfig();
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
        $config = $this->getConfig();
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
