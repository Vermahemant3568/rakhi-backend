<?php

namespace App\Services\Nutrition;

class NutritionEstimator
{
    protected array $foodDb = [
        'roti' => ['protein' => 3, 'carbs' => 15, 'calories' => 70],
        'dal' => ['protein' => 7, 'carbs' => 12, 'calories' => 120],
        'rice' => ['protein' => 4, 'carbs' => 45, 'calories' => 200],
        'sabzi' => ['protein' => 2, 'carbs' => 8, 'calories' => 50],
        'chapati' => ['protein' => 3, 'carbs' => 15, 'calories' => 70],
        'curry' => ['protein' => 5, 'carbs' => 10, 'calories' => 100],
        'mixed_food' => ['protein' => 10, 'carbs' => 30, 'calories' => 250]
    ];

    public function estimate(array $foods): array
    {
        $total = ['protein' => 0, 'carbs' => 0, 'calories' => 0];

        foreach ($foods as $food) {
            if (!isset($this->foodDb[$food['name']])) continue;

            $data = $this->foodDb[$food['name']];
            $multiplier = $this->getQuantityMultiplier($food['quantity']);

            foreach ($total as $k => $v) {
                $total[$k] += $data[$k] * $multiplier;
            }
        }

        return [
            'protein' => round($total['protein'], 1),
            'carbs' => round($total['carbs'], 1),
            'calories' => round($total['calories'], 1),
            'disclaimer' => 'लगभग / Approximate values'
        ];
    }

    private function getQuantityMultiplier(string $quantity): float
    {
        // Simple quantity parsing
        if (str_contains($quantity, '2')) return 2.0;
        if (str_contains($quantity, '3')) return 3.0;
        if (str_contains($quantity, 'bowl')) return 1.0;
        if (str_contains($quantity, 'plate')) return 1.0;
        
        return 1.0; // Default
    }
}