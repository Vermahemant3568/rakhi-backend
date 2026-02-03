<?php

namespace App\Services\AI;

use App\Models\RakhiRule;
use App\Models\PromptTemplate;
use Illuminate\Support\Facades\Log;

class PromptBuilder
{
    public function build(string $userMessage, string $context = '', string $templateType = 'chat'): string
    {
        try {
            // Get active rules from admin panel
            $personality = RakhiRule::where('key', 'personality')
                ->where('is_active', true)
                ->value('value') ?? 'compassionate and supportive';
                
            $tone = RakhiRule::where('key', 'tone')
                ->where('is_active', true)
                ->value('value') ?? 'warm and understanding';
                
            $responseStyle = RakhiRule::where('key', 'response_style')
                ->where('is_active', true)
                ->value('value') ?? 'empathetic and helpful';

            // Get active template from admin panel
            $template = PromptTemplate::where('type', $templateType)
                ->where('is_active', true)
                ->value('template');

            // Fallback template if none configured
            if (!$template) {
                $template = $this->getDefaultTemplate($templateType);
                Log::warning("No active {$templateType} template found, using default");
            }

            // Build dynamic prompt with admin-controlled variables
            $prompt = $this->replacePlaceholders($template, [
                '{personality}' => $personality,
                '{tone}' => $tone,
                '{response_style}' => $responseStyle,
                '{context}' => $context,
                '{user_message}' => $userMessage,
                '{app_name}' => config('app.name', 'Rakhi')
            ]);

            return $prompt;
            
        } catch (\Exception $e) {
            Log::error('PromptBuilder Error: ' . $e->getMessage());
            return $this->getEmergencyPrompt($userMessage, $context);
        }
    }
    
    private function replacePlaceholders(string $template, array $variables): string
    {
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    private function getDefaultTemplate(string $type): string
    {
        $templates = [
            'chat' => "You are {app_name}, a {personality} AI companion with a {tone} tone.\n\nPersonality: {personality}\nTone: {tone}\nResponse Style: {response_style}\n\nContext: {context}\n\nUser says: {user_message}\n\nRespond with empathy and understanding.",
            'voice' => "You are {app_name} in a voice conversation. Be {personality} with a {tone} tone. Keep responses concise for voice.\n\nContext: {context}\nUser: {user_message}\n\nRespond naturally:",
            'emergency' => "EMERGENCY PROTOCOL: You are {app_name}. The user may be in crisis.\n\nUser: {user_message}\n\nProvide immediate support and encourage professional help if needed.",
            'disclaimer' => "I am {app_name}, an AI companion. I'm not a replacement for professional medical or mental health care.\n\nUser: {user_message}\n\nHow can I support you today?",
            'followup' => "Following up on our previous conversation.\n\nContext: {context}\nUser: {user_message}\n\nContinuing our supportive dialogue:"
        ];
        
        return $templates[$type] ?? $templates['chat'];
    }
    
    private function getEmergencyPrompt(string $userMessage, string $context): string
    {
        return "I understand you're reaching out. While I'm here to listen and support you, I want to make sure you get the best help possible.\n\nUser: {$userMessage}\n\nHow can I support you right now?";
    }
}