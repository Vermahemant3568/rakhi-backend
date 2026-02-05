<?php

namespace App\Services\Nutrition;

use App\Services\Memory\MemorySelectorService;

class NutritionInsightService
{
    protected $memorySelectorService;
    protected $mealPatternAnalyzer;

    public function __construct()
    {
        $this->memorySelectorService = new MemorySelectorService();
        $this->mealPatternAnalyzer = new MealPatternAnalyzer();
    }

    public function generateNutritionInsights(int $userId, string $currentMessage): array
    {
        // Get meal-related memories
        $mealMemories = $this->memorySelectorService->getRelevantMemories($userId, $currentMessage, 10);
        
        // Filter for meal pattern memories
        $mealPatternMemories = array_filter($mealMemories, function($memory) {
            return $memory['type'] === 'meal_pattern';
        });

        $insights = [
            'consistency_insights' => $this->analyzeConsistencyPatterns($mealPatternMemories),
            'food_pattern_insights' => $this->analyzeFoodPatterns($mealPatternMemories),
            'timing_insights' => $this->analyzeTimingPatterns($mealPatternMemories),
            'coaching_suggestions' => $this->generateCoachingSuggestions($mealPatternMemories)
        ];

        return $insights;
    }

    protected function analyzeConsistencyPatterns(array $memories): array
    {
        $insights = [];
        $patterns = [];
        
        foreach ($memories as $memory) {
            $metadata = $memory['metadata'] ?? [];
            $foodPattern = $metadata['food_pattern'] ?? 'unknown';
            $mealType = $metadata['meal_type'] ?? 'unknown';
            
            if (!isset($patterns[$mealType])) {
                $patterns[$mealType] = [];
            }
            $patterns[$mealType][] = $foodPattern;
        }

        foreach ($patterns as $mealType => $foodPatterns) {
            $homeCookedCount = count(array_filter($foodPatterns, fn($p) => $p === 'home_cooked'));
            $totalCount = count($foodPatterns);
            
            if ($totalCount >= 3) {
                $consistency = $homeCookedCount / $totalCount;
                
                if ($consistency >= 0.7) {
                    $insights[] = "Main notice kar rahi hoon aap {$mealType} mostly ghar ka lete ho — great consistency 👏";
                } elseif ($consistency <= 0.3) {
                    $insights[] = "Aap {$mealType} mein outside food zyada kar rahe hain — balance ke liye home food try karein";
                }
            }
        }

        return $insights;
    }

    protected function analyzeFoodPatterns(array $memories): array
    {
        $insights = [];
        $patternCounts = [];
        
        foreach ($memories as $memory) {
            $metadata = $memory['metadata'] ?? [];
            $pattern = $metadata['food_pattern'] ?? 'unknown';
            $patternCounts[$pattern] = ($patternCounts[$pattern] ?? 0) + 1;
        }

        $totalMeals = array_sum($patternCounts);
        
        if ($totalMeals >= 5) {
            $healthyRatio = ($patternCounts['healthy_choice'] ?? 0) / $totalMeals;
            $junkRatio = ($patternCounts['junk_food'] ?? 0) / $totalMeals;
            
            if ($healthyRatio >= 0.6) {
                $insights[] = "Healthy food choices ka pattern excellent hai aap ka — keep it up!";
            }
            
            if ($junkRatio >= 0.4) {
                $insights[] = "Junk food ka intake thoda zyada ho raha hai — healthy alternatives try karein";
            }
        }

        return $insights;
    }

    protected function analyzeTimingPatterns(array $memories): array
    {
        $insights = [];
        $timingData = [];
        
        foreach ($memories as $memory) {
            $metadata = $memory['metadata'] ?? [];
            $mealType = $metadata['meal_type'] ?? 'unknown';
            $timePeriod = $metadata['time_period'] ?? 'unknown';
            
            if (!isset($timingData[$mealType])) {
                $timingData[$mealType] = [];
            }
            $timingData[$mealType][] = $timePeriod;
        }

        foreach ($timingData as $mealType => $periods) {
            $consistentPeriods = array_count_values($periods);
            $mostCommon = array_keys($consistentPeriods, max($consistentPeriods))[0];
            $consistency = max($consistentPeriods) / count($periods);
            
            if ($consistency >= 0.8 && count($periods) >= 3) {
                $insights[] = "Aap ka {$mealType} timing bahut consistent hai — excellent discipline!";
            }
        }

        return $insights;
    }

    protected function generateCoachingSuggestions(array $memories): array
    {
        $suggestions = [];
        
        if (empty($memories)) {
            $suggestions[] = "Meal tracking start kariye — consistency maintain karne mein help milegi";
            return $suggestions;
        }

        // Analyze recent patterns for suggestions
        $recentMemories = array_slice($memories, 0, 5);
        $outsideFoodCount = 0;
        $totalRecent = count($recentMemories);
        
        foreach ($recentMemories as $memory) {
            $metadata = $memory['metadata'] ?? [];
            if (($metadata['food_pattern'] ?? '') === 'outside_food') {
                $outsideFoodCount++;
            }
        }

        if ($outsideFoodCount / $totalRecent > 0.6) {
            $suggestions[] = "Recent mein outside food zyada hai — meal prep try kariye home food ke liye";
        }

        // Add general suggestions based on patterns
        $suggestions[] = "Consistent meal timing maintain kariye — metabolism ke liye better hai";
        $suggestions[] = "Home cooked food prefer kariye — nutrition aur portion control better hota hai";

        return $suggestions;
    }
}