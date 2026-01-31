<?php

namespace App\Services\AI;

use App\Services\AI\PromptBuilder;
use App\Models\RakhiRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function reply(string $userMessage, string $context = ''): string
    {
        // Admin kill switch - check if AI is enabled
        if (!RakhiRule::where('key','ai_enabled')->value('value')) {
            return "Rakhi is temporarily unavailable. Please try again later.";
        }
        
        try {
            $apiKey = config('services.gemini.api_key');
            
            // Build dynamic prompt using admin configuration
            $prompt = (new PromptBuilder())->build($userMessage, $context);
            
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'I understand you. Let\'s work on this together 💙';
            }
            
            Log::error('Gemini API Error: ' . $response->body());
            return 'I understand you. Let\'s work on this together 💙';
            
        } catch (\Exception $e) {
            Log::error('Gemini Service Error: ' . $e->getMessage());
            return 'I understand you. Let\'s work on this together 💙';
        }
    }
}