<?php

namespace App\Services\NLP;

class EmotionService
{
    public function detect(string $text): string
    {
        $text = strtolower($text);
        
        // Guilt - high priority for coaching
        if (preg_match('/\b(guilty|shame|ashamed|regret|failed|shouldn\'t have|bad about|disappointed in myself|let myself down)\b/', $text)) {
            return 'guilt';
        }
        
        // Stress - needs immediate support
        if (preg_match('/\b(stressed|overwhelmed|pressure|anxious|worried|nervous|panic|tension|can\'t handle)\b/', $text)) {
            return 'stress';
        }
        
        // Tired/exhausted - affects motivation
        if (preg_match('/\b(tired|exhausted|drained|no energy|fatigue|worn out|burnt out|sleepy)\b/', $text)) {
            return 'tired';
        }
        
        // Proud - celebrate wins
        if (preg_match('/\b(proud|accomplished|achieved|success|did it|made it|happy with|satisfied|good about)\b/', $text)) {
            return 'proud';
        }
        
        // Confused - needs guidance
        if (preg_match('/\b(confused|lost|don\'t know|uncertain|unsure|stuck|don\'t understand|unclear)\b/', $text)) {
            return 'confused';
        }
        
        // Frustrated - needs patience
        if (preg_match('/\b(frustrated|annoyed|irritated|fed up|sick of|can\'t take|enough)\b/', $text)) {
            return 'frustrated';
        }
        
        // Sad/down - needs empathy
        if (preg_match('/\b(sad|depressed|down|upset|disappointed|hopeless|defeated|low|blue)\b/', $text)) {
            return 'sad';
        }
        
        // Angry - needs calm approach
        if (preg_match('/\b(angry|mad|furious|pissed|hate|disgusted|outraged)\b/', $text)) {
            return 'angry';
        }
        
        // Motivated/positive - encourage momentum
        if (preg_match('/\b(motivated|excited|ready|determined|confident|positive|energetic|pumped|inspired)\b/', $text)) {
            return 'motivated';
        }
        
        // Happy/content
        if (preg_match('/\b(happy|great|amazing|wonderful|fantastic|good|fine|okay|alright)\b/', $text)) {
            return 'positive';
        }
        
        return 'neutral';
    }
}