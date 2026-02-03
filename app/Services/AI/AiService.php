<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Services\AI\PromptBuilder;
use App\Models\RakhiRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    public function reply(string $userMessage, string $context = '', string $templateType = 'chat'): string
    {
        // Admin kill switch - check if AI is enabled
        if (!RakhiRule::where('key','ai_enabled')->value('value')) {
            return "Rakhi is temporarily unavailable. Please try again later.";
        }
        
        // Get active AI model
        $activeModel = AiModel::where('is_active', true)->first();
        
        if (!$activeModel) {
            Log::error('No active AI model found');
            return 'I understand you. Let\'s work on this together 💙';
        }
        
        try {
            // Build dynamic prompt using admin configuration
            $prompt = (new PromptBuilder())->build($userMessage, $context, $templateType);
            
            // Route to appropriate service based on provider
            switch ($activeModel->provider) {
                case 'gemini':
                    return $this->callGemini($activeModel->model_name, $prompt);
                case 'openai':
                    return $this->callOpenAI($activeModel->model_name, $prompt);
                case 'claude':
                    return $this->callClaude($activeModel->model_name, $prompt);
                default:
                    Log::error('Unknown AI provider: ' . $activeModel->provider);
                    return 'I understand you. Let\'s work on this together 💙';
            }
            
        } catch (\Exception $e) {
            Log::error('AI Service Error: ' . $e->getMessage());
            return 'I understand you. Let\'s work on this together 💙';
        }
    }
    
    private function callGemini(string $model, string $prompt): string
    {
        $apiKey = env('GEMINI_API_KEY');
        
        if (!$apiKey) {
            Log::error('Gemini API key not configured');
            return 'I understand you. Let\'s work on this together 💙';
        }
        
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
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
    }
    
    private function callOpenAI(string $model, string $prompt): string
    {
        $apiKey = env('OPENAI_API_KEY');
        
        if (!$apiKey) {
            Log::error('OpenAI API key not configured');
            return 'I understand you. Let\'s work on this together 💙';
        }
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1000
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? 'I understand you. Let\'s work on this together 💙';
        }
        
        Log::error('OpenAI API Error: ' . $response->body());
        return 'I understand you. Let\'s work on this together 💙';
    }
    
    private function callClaude(string $model, string $prompt): string
    {
        $apiKey = env('CLAUDE_API_KEY');
        
        if (!$apiKey) {
            Log::error('Claude API key not configured');
            return 'I understand you. Let\'s work on this together 💙';
        }
        
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 1000,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['content'][0]['text'] ?? 'I understand you. Let\'s work on this together 💙';
        }
        
        Log::error('Claude API Error: ' . $response->body());
        return 'I understand you. Let\'s work on this together 💙';
    }
}