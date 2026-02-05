<?php

namespace App\Services\Coach;

use App\Models\Goal;
use App\Models\Message;
use Carbon\Carbon;

class HabitFollowUpLogic
{
    public function generateFollowUp($user, array $coachAnalysis): ?string
    {
        $goals = $coachAnalysis['user_goals'];
        $intent = $coachAnalysis['intent'];
        $memories = $coachAnalysis['memories'];

        // Time-based follow-ups
        $timeBasedFollowUp = $this->getTimeBasedFollowUp($user->id, $goals);
        if ($timeBasedFollowUp) {
            return $timeBasedFollowUp;
        }

        // Goal-specific follow-ups
        $goalFollowUp = $this->getGoalSpecificFollowUp($goals, $intent);
        if ($goalFollowUp) {
            return $goalFollowUp;
        }

        // Memory-based follow-ups
        return $this->getMemoryBasedFollowUp($memories);
    }

    protected function getTimeBasedFollowUp(int $userId, array $goals): ?string
    {
        $hour = now()->hour;
        $lastMessage = Message::where('conversation_id', function($q) use ($userId) {
            $q->select('id')->from('conversations')
              ->where('user_id', $userId)
              ->where('status', 'active')
              ->first();
        })->where('sender', 'user')
          ->latest()
          ->first();

        // Morning follow-ups (6-11 AM)
        if ($hour >= 6 && $hour <= 11) {
            if ($this->hasGoalType($goals, 'weight_loss') || $this->hasGoalType($goals, 'fitness')) {
                return "Subah ka diet plan follow hua? Breakfast mein kya liya?";
            }
            return "Good morning! Aaj ka plan ready hai?";
        }

        // Afternoon follow-ups (12-5 PM)
        if ($hour >= 12 && $hour <= 17) {
            if ($this->hasGoalType($goals, 'weight_loss')) {
                return "Lunch heavy toh nahi tha? Portion control kaise raha?";
            }
            if ($this->hasGoalType($goals, 'fitness')) {
                return "Aaj workout ka time nikla? Ya evening mein plan hai?";
            }
        }

        // Evening follow-ups (6-9 PM)
        if ($hour >= 18 && $hour <= 21) {
            if ($lastMessage && $lastMessage->created_at->isYesterday()) {
                return "Kal jo plan banaya tha uska kya hua? Progress share karo!";
            }
        }

        return null;
    }

    protected function getGoalSpecificFollowUp(array $goals, string $intent): ?string
    {
        // Intent-specific follow-ups
        switch ($intent) {
            case 'meal_logging':
                return "Meal ka portion size kaisa tha? Aur kya healthy choices add kar sakte hain?";
                
            case 'emotional_eating':
                return "Emotional eating ke triggers identify kar rahe hain? Koi alternative coping strategies try kar sakte hain?";
                
            case 'motivation_drop':
                return "Motivation low feel ho raha hai, ye normal hai. Kya small step se restart kar sakte hain?";
                
            case 'progress_check':
                return "Progress review karte hain - kya improvements notice kiye hain? Next milestone kya set karein?";
        }
        
        // Goal-based follow-ups
        foreach ($goals as $goal) {
            $goalName = strtolower($goal['title']);
            
            if (str_contains($goalName, 'weight') && in_array($intent, ['meal_logging', 'emotional_eating'])) {
                return "Weight loss journey mein ye food choice kaise fit ho rahi hai? Koi adjustments needed?";
            }
            
            if (str_contains($goalName, 'fitness') && $intent === 'exercise_update') {
                return "Fitness goal ke liye consistency maintain kar rahe ho? Next workout plan kya hai?";
            }
            
            if (str_contains($goalName, 'habit') && $intent === 'habit_check') {
                return "Daily habits track ho rahe hain consistently? Koi challenges face kar rahe ho?";
            }
        }

        return null;
    }

    protected function getMemoryBasedFollowUp(array $memories): ?string
    {
        foreach ($memories as $memory) {
            $content = strtolower($memory['content']);
            
            if (str_contains($content, 'plan') && str_contains($content, 'tomorrow')) {
                return "Jo plan banaya tha, uska update do! Kaise chal raha hai?";
            }
            
            if (str_contains($content, 'diet') && $memory['type'] === 'food') {
                return "Diet plan follow ho raha hai consistently? Koi challenges face kar rahe ho?";
            }
            
            if (str_contains($content, 'exercise') && $memory['type'] === 'exercise') {
                return "Exercise routine mein koi changes karne hain? Ya same continue kar rahe ho?";
            }
        }

        return null;
    }

    protected function hasGoalType(array $goals, string $type): bool
    {
        foreach ($goals as $goal) {
            if (str_contains(strtolower($goal['name']), $type)) {
                return true;
            }
        }
        return false;
    }
}