<?php

namespace App\Services\Nutrition;

use App\Services\Memory\MemoryManager;
use App\Models\Message;
use Carbon\Carbon;

class MealPatternAnalyzer
{
    protected $memoryManager;

    public function __construct()
    {
        $this->memoryManager = new MemoryManager();
    }

    public function analyzeMealPattern(int $userId, string $message, string $intent): array
    {
        $analysis = [
            'meal_type' => $this->detectMealType($message),
            'food_pattern' => $this->detectFoodPattern($message),
            'location' => $this->detectMealLocation($message),
            'consistency_score' => 0,
            'insights' => []
        ];

        // Get historical meal data
        $mealHistory = $this->getMealHistory($userId, 7); // Last 7 days
        
        // Analyze consistency patterns
        $analysis['consistency_score'] = $this->calculateConsistency($mealHistory, $analysis);
        $analysis['insights'] = $this->generateInsights($mealHistory, $analysis);

        return $analysis;
    }

    public function storeMealMemory(int $userId, string $message, array $mealAnalysis): void
    {
        $metadata = [
            'meal_type' => $mealAnalysis['meal_type'],
            'food_pattern' => $mealAnalysis['food_pattern'],
            'location' => $mealAnalysis['location'],
            'consistency_score' => $mealAnalysis['consistency_score'],
            'time_period' => $this->getCurrentTimePeriod(),
            'date' => now()->toDateString()
        ];

        $this->memoryManager->storeMemory($userId, 'meal_pattern', $message, $metadata);
    }

    protected function detectMealType(string $message): string
    {
        $message = strtolower($message);
        
        if (preg_match('/\b(breakfast|subah|morning|nashta)\b/', $message)) {
            return 'breakfast';
        }
        if (preg_match('/\b(lunch|dopahar|afternoon)\b/', $message)) {
            return 'lunch';
        }
        if (preg_match('/\b(dinner|raat|night|shaam)\b/', $message)) {
            return 'dinner';
        }
        if (preg_match('/\b(snack|chai|coffee|biscuit)\b/', $message)) {
            return 'snack';
        }
        
        // Time-based detection
        $hour = now()->hour;
        if ($hour >= 6 && $hour <= 10) return 'breakfast';
        if ($hour >= 11 && $hour <= 15) return 'lunch';
        if ($hour >= 16 && $hour <= 18) return 'snack';
        if ($hour >= 19 && $hour <= 22) return 'dinner';
        
        return 'unknown';
    }

    protected function detectFoodPattern(string $message): string
    {
        $message = strtolower($message);
        
        // Home food patterns
        if (preg_match('/\b(ghar|home|mummy|mom|homemade|dal|roti|chawal|sabzi)\b/', $message)) {
            return 'home_cooked';
        }
        
        // Outside food patterns
        if (preg_match('/\b(restaurant|hotel|office|canteen|order|delivery|zomato|swiggy)\b/', $message)) {
            return 'outside_food';
        }
        
        // Healthy patterns
        if (preg_match('/\b(salad|fruits|juice|oats|healthy|diet)\b/', $message)) {
            return 'healthy_choice';
        }
        
        // Junk food patterns
        if (preg_match('/\b(pizza|burger|chips|fried|junk|fast food)\b/', $message)) {
            return 'junk_food';
        }
        
        return 'regular';
    }

    protected function detectMealLocation(string $message): string
    {
        $message = strtolower($message);
        
        if (preg_match('/\b(ghar|home)\b/', $message)) {
            return 'home';
        }
        if (preg_match('/\b(office|work)\b/', $message)) {
            return 'office';
        }
        if (preg_match('/\b(restaurant|hotel|outside)\b/', $message)) {
            return 'restaurant';
        }
        
        return 'unknown';
    }

    protected function getMealHistory(int $userId, int $days): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        return Message::whereHas('conversation', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('sender', 'user')
        ->where('created_at', '>=', $startDate)
        ->where('intent', 'meal_logging')
        ->get()
        ->map(function($message) {
            return [
                'message' => $message->message,
                'date' => $message->created_at->toDateString(),
                'hour' => $message->created_at->hour
            ];
        })
        ->toArray();
    }

    protected function calculateConsistency(array $history, array $currentAnalysis): float
    {
        if (empty($history)) return 0.0;
        
        $consistencyFactors = [];
        
        // Check meal timing consistency
        $timingConsistency = $this->checkTimingConsistency($history, $currentAnalysis['meal_type']);
        $consistencyFactors[] = $timingConsistency;
        
        // Check food pattern consistency
        $patternConsistency = $this->checkPatternConsistency($history, $currentAnalysis['food_pattern']);
        $consistencyFactors[] = $patternConsistency;
        
        return array_sum($consistencyFactors) / count($consistencyFactors);
    }

    protected function checkTimingConsistency(array $history, string $mealType): float
    {
        $mealTimes = [
            'breakfast' => [6, 10],
            'lunch' => [11, 15], 
            'dinner' => [19, 22]
        ];
        
        if (!isset($mealTimes[$mealType])) return 0.5;
        
        $consistentMeals = 0;
        $totalMeals = 0;
        
        foreach ($history as $meal) {
            $hour = $meal['hour'];
            if ($hour >= $mealTimes[$mealType][0] && $hour <= $mealTimes[$mealType][1]) {
                $consistentMeals++;
            }
            $totalMeals++;
        }
        
        return $totalMeals > 0 ? $consistentMeals / $totalMeals : 0.0;
    }

    protected function checkPatternConsistency(array $history, string $currentPattern): float
    {
        $patternCount = 0;
        $totalMeals = count($history);
        
        foreach ($history as $meal) {
            $historicalPattern = $this->detectFoodPattern($meal['message']);
            if ($historicalPattern === $currentPattern) {
                $patternCount++;
            }
        }
        
        return $totalMeals > 0 ? $patternCount / $totalMeals : 0.0;
    }

    protected function generateInsights(array $history, array $analysis): array
    {
        $insights = [];
        
        // Home food consistency insight
        if ($analysis['food_pattern'] === 'home_cooked' && $analysis['consistency_score'] > 0.7) {
            $insights[] = "Main notice kar rahi hoon aap {$analysis['meal_type']} mostly ghar ka lete ho — great consistency 👏";
        }
        
        // Healthy pattern insight
        if ($analysis['food_pattern'] === 'healthy_choice') {
            $insights[] = "Healthy choices kar rahe hain aap — ye pattern maintain karna important hai";
        }
        
        // Outside food pattern
        if ($analysis['food_pattern'] === 'outside_food' && $analysis['consistency_score'] > 0.6) {
            $insights[] = "Outside food ka pattern zyada ho raha hai — balance ke liye home food try karein";
        }
        
        // Timing consistency
        if ($analysis['consistency_score'] > 0.8) {
            $insights[] = "Meal timing bahut consistent hai aap ka — excellent discipline!";
        }
        
        return $insights;
    }

    protected function getCurrentTimePeriod(): string
    {
        $hour = now()->hour;
        
        if ($hour >= 6 && $hour <= 11) return 'morning';
        if ($hour >= 12 && $hour <= 17) return 'afternoon';
        if ($hour >= 18 && $hour <= 21) return 'evening';
        return 'night';
    }
}