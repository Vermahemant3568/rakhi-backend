<?php

namespace App\Services\AI;

use App\Models\LlmConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatGPTService
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
        $config = LlmConfig::where('provider', 'chatgpt')
                           ->where('is_active', 1)
                           ->first();

        if (!$config) {
            throw new \Exception('ChatGPT LLM config not found. Please activate it from admin panel.');
        }

        return $config;
    }

    public function chat(string $prompt, array $history = []): string
    {
        $config = $this->getConfig();
        $apiKey = $this->decryptKey($config->api_key);
        $model  = $config->model_name ?? 'gpt-4o';

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

        $messages = array_map(function ($msg) {
            $msg['content'] = mb_convert_encoding($msg['content'], 'UTF-8', 'UTF-8');
            return $msg;
        }, $messages);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(25)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $config->max_tokens ?? 220,
            'temperature' => (float) ($config->temperature ?? 0.65),
            'top_p'       => (float) ($config->top_p ?? 0.85),
        ]);

        if ($response->failed()) {
            Log::error('ChatGPT API failed: ' . $response->body());
            throw new \Exception('ChatGPT API error');
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    public function embed(string $text): array
    {
        $config = $this->getConfig();
        $apiKey = $this->decryptKey($config->api_key);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post('https://api.openai.com/v1/embeddings', [
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->json('data.0.embedding') ?? [];
    }
}
