<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function embed(string $text): array
    {
        try {
            $apiKey = env('GEMINI_API_KEY');
            
            if (!$apiKey) {
                Log::error('Gemini API key not configured for embeddings');
                return [];
            }
            
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key={$apiKey}", [
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['embedding']['values'] ?? [];
            }
            
            Log::error('Gemini Embedding API Error: ' . $response->body());
            return [];
            
        } catch (\Exception $e) {
            Log::error('Embedding Service Error: ' . $e->getMessage());
            return [];
        }
    }
}