<?php

namespace App\Services\Coach;

use Carbon\Carbon;

class TimeAwarenessService
{
    public function getTimeContext(): array
    {
        $hour = Carbon::now()->hour;
        
        if ($hour >= 6 && $hour < 11) {
            return [
                'period' => 'morning',
                'meal_context' => 'breakfast',
                'greeting' => 'Good morning',
                'meal_question' => 'Aaj subah kya khaya?'
            ];
        }
        
        if ($hour >= 11 && $hour < 16) {
            return [
                'period' => 'afternoon',
                'meal_context' => 'lunch',
                'greeting' => 'Good afternoon',
                'meal_question' => 'Lunch mein kya include tha?'
            ];
        }
        
        if ($hour >= 16 && $hour < 20) {
            return [
                'period' => 'evening',
                'meal_context' => 'snack',
                'greeting' => 'Good evening',
                'meal_question' => 'Shaam ko craving hui?'
            ];
        }
        
        return [
            'period' => 'night',
            'meal_context' => 'dinner',
            'greeting' => 'Good evening',
            'meal_question' => 'Dinner mein kya tha?'
        ];
    }
    
    public function getMealTimeAdvice(string $mealType): string
    {
        $advice = [
            'breakfast' => 'Subah ka khana energy ke liye important hai',
            'lunch' => 'Lunch balanced hona chahiye - protein aur carbs dono',
            'snack' => 'Evening snack light rakhiye',
            'dinner' => 'Dinner early aur light lena better hai'
        ];
        
        return $advice[$mealType] ?? 'Meal timing maintain karna important hai';
    }
}