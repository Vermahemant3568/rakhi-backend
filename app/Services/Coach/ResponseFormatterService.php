<?php

namespace App\Services\Coach;

class ResponseFormatterService
{
    public function formatResponse(string $response): string
    {
        // Clean up the response
        $response = $this->cleanResponse($response);
        
        // Break into readable chunks if too long
        if (strlen($response) > 300) {
            return $this->breakIntoChunks($response);
        }
        
        return $this->makeUserFriendly($response);
    }

    protected function cleanResponse(string $response): string
    {
        // Remove excessive formatting
        $response = preg_replace('/\*\s*\*\*([^*]+)\*\*/', '$1', $response);
        $response = preg_replace('/\*\*([^*]+)\*\*/', '$1', $response);
        $response = preg_replace('/\*([^*]+)\*/', '$1', $response);
        
        // Clean bullet points
        $response = preg_replace('/^\s*[\*\-\•]\s*/m', '• ', $response);
        
        // Remove extra spaces and line breaks
        $response = preg_replace('/\n\s*\n\s*\n/', "\n\n", $response);
        $response = trim($response);
        
        return $response;
    }

    protected function breakIntoChunks(string $response): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $response);
        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk . $sentence) > 200 && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        // Join chunks with double line breaks for readability
        return implode("\n\n", array_map([$this, 'makeUserFriendly'], $chunks));
    }

    protected function makeUserFriendly(string $text): string
    {
        // Add friendly touches
        $text = $this->addBreathingSpace($text);
        $text = $this->simplifyLanguage($text);
        
        return $text;
    }

    protected function addBreathingSpace(string $text): string
    {
        // Add space after periods for better readability
        $text = preg_replace('/\.([A-Z])/', '. $1', $text);
        
        // Add space around bullet points
        $text = preg_replace('/•\s*/', '• ', $text);
        
        return $text;
    }

    protected function simplifyLanguage(string $text): string
    {
        // Make language more conversational
        $replacements = [
            'It is' => 'It\'s',
            'You are' => 'Aap',
            'cannot' => 'can\'t',
            'do not' => 'don\'t',
            'will not' => 'won\'t',
            'should not' => 'shouldn\'t',
            'altogether' => 'completely',
            'effectively' => 'properly',
            'diagnosed' => 'found',
            'autoimmune condition' => 'body condition',
            'insulin resistance' => 'insulin not working well'
        ];
        
        foreach ($replacements as $formal => $casual) {
            $text = str_ireplace($formal, $casual, $text);
        }
        
        return $text;
    }
}