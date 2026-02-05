<?php

namespace App\Services\Coach;

use Carbon\Carbon;

class TimeAwareCoachingService
{
    public function getTimeContext($user): array
    {
        $userTimezone = $user->profile->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($userTimezone);
        
        return [
            'current_time' => $now,
            'hour' => $now->hour,
            'time_period' => $this->getTimePeriod($now->hour),
            'timezone' => $userTimezone,
            'day_part' => $this->getDayPart($now->hour)
        ];
    }

    public function getTimeBasedPrompt(array $timeContext, string $intent): ?string
    {
        $hour = $timeContext['hour'];
        $timePeriod = $timeContext['time_period'];
        
        // Morning prompts (6 AM - 11 AM)
        if ($timePeriod === 'morning') {
            switch ($intent) {
                case 'meal_logging':
                    return "Good morning! Breakfast mein kya liya? Healthy start hua?";
                case 'general':
                    return "Subah ka routine kaisa chal raha hai? Aaj ka plan ready hai?";
                case 'exercise_update':
                    return "Morning workout ho gaya? Ya evening mein plan hai?";
            }
        }
        
        // Afternoon prompts (12 PM - 5 PM)
        if ($timePeriod === 'afternoon') {
            switch ($intent) {
                case 'meal_logging':
                    return "Lunch balanced tha ya heavy? Portion size kaisa tha?";
                case 'general':
                    return "Afternoon energy levels kaise hain? Koi healthy snack liya?";
                case 'exercise_update':
                    return "Lunch ke baad walk kar sakte ho? Ya evening workout planned hai?";
            }
        }
        
        // Evening prompts (6 PM - 9 PM)
        if ($timePeriod === 'evening') {
            switch ($intent) {
                case 'meal_logging':
                    return "Dinner light rakha kya? Early dinner better hota hai.";
                case 'general':
                    return "Evening routine mein kya include kiya? Relaxation time mila?";
                case 'exercise_update':
                    return "Evening walk ho gaya? Ya koi light activity kiya?";
            }
        }
        
        // Night prompts (10 PM - 5 AM)
        if ($timePeriod === 'night') {
            switch ($intent) {
                case 'meal_logging':
                    return "Late night eating avoid kiya? Sleep ke liye better hai.";
                case 'general':
                    return "Aaj ka day review karte hain. Kya achieve kiya?";
                case 'exercise_update':
                    return "Kal ke liye workout plan ready hai? Good rest lena important hai.";
            }
        }
        
        return null;
    }

    public function getTimeBasedFollowUp(array $timeContext, array $goals): ?string
    {
        $hour = $timeContext['hour'];
        
        // Morning follow-ups (7-10 AM)
        if ($hour >= 7 && $hour <= 10) {
            if ($this->hasGoal($goals, 'weight_loss')) {
                return "Subah ka diet plan follow hua? Water intake start kiya?";
            }
            return "Aaj ke goals set kiye? Morning routine complete hua?";
        }
        
        // Lunch time (12-2 PM)
        if ($hour >= 12 && $hour <= 14) {
            if ($this->hasGoal($goals, 'weight_loss')) {
                return "Lunch mein portion control kiya? Vegetables include kiye?";
            }
            return "Lunch break properly liya? Energy levels kaise hain?";
        }
        
        // Evening (6-8 PM)
        if ($hour >= 18 && $hour <= 20) {
            if ($this->hasGoal($goals, 'fitness')) {
                return "Evening workout time hai! Kya plan hai aaj?";
            }
            return "Dinner preparation start kiya? Light dinner better hota hai.";
        }
        
        // Night time (9-11 PM)
        if ($hour >= 21 && $hour <= 23) {
            return "Aaj ka reflection - kya goals achieve kiye? Kal ke liye ready?";
        }
        
        return null;
    }

    public function shouldAskTimeBasedQuestion($user): bool
    {
        $lastActivity = $user->messages()->latest()->first();
        
        if (!$lastActivity) {
            return true;
        }
        
        // Ask time-based questions if last activity was more than 4 hours ago
        return $lastActivity->created_at->diffInHours(now()) >= 4;
    }

    protected function getTimePeriod(int $hour): string
    {
        if ($hour >= 6 && $hour <= 11) {
            return 'morning';
        }
        if ($hour >= 12 && $hour <= 17) {
            return 'afternoon';
        }
        if ($hour >= 18 && $hour <= 21) {
            return 'evening';
        }
        return 'night';
    }

    protected function getDayPart(int $hour): string
    {
        if ($hour >= 5 && $hour <= 11) {
            return 'early_morning';
        }
        if ($hour >= 12 && $hour <= 16) {
            return 'midday';
        }
        if ($hour >= 17 && $hour <= 20) {
            return 'early_evening';
        }
        if ($hour >= 21 && $hour <= 23) {
            return 'late_evening';
        }
        return 'night';
    }

    protected function hasGoal(array $goals, string $type): bool
    {
        foreach ($goals as $goal) {
            if (str_contains(strtolower($goal['title']), $type)) {
                return true;
            }
        }
        return false;
    }
}