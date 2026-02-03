<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\ReportGenerator;

class ReportTriggerService
{
    protected $reportGenerator;

    public function __construct(ReportGenerator $reportGenerator)
    {
        $this->reportGenerator = $reportGenerator;
    }

    // 1️⃣ Consultation Report - Generated after voice call or long chat
    public function generateConsultationReport(User $user, array $conversationData)
    {
        $data = [
            'user' => $user,
            'date' => now()->format('M d, Y'),
            'summary' => $conversationData['summary'] ?? 'Consultation completed',
            'notes' => $conversationData['notes'] ?? []
        ];

        return $this->reportGenerator->generate($user, 'consultation', $data);
    }

    // 2️⃣ Diet Plan Report - Generated after first consultation or on request
    public function generateDietPlanReport(User $user)
    {
        $data = [
            'user' => $user,
            'date' => now()->format('M d, Y'),
            'goal' => $user->goals->pluck('title')->implode(', ') ?: 'General wellness',
            'meals' => $this->getDietPlan($user),
            'guidelines' => $this->getDietGuidelines($user)
        ];

        return $this->reportGenerator->generate($user, 'diet', $data);
    }

    // 3️⃣ Fitness Plan Report - Generated on request or admin trigger
    public function generateFitnessPlanReport(User $user)
    {
        $data = [
            'user' => $user,
            'date' => now()->format('M d, Y'),
            'goal' => $user->goals->pluck('title')->implode(', ') ?: 'General fitness',
            'exercises' => $this->getFitnessplan($user),
            'tips' => $this->getFitnessTips($user)
        ];

        return $this->reportGenerator->generate($user, 'fitness', $data);
    }

    private function getDietPlan(User $user)
    {
        // Basic diet plan based on user profile
        return [
            ['time' => 'Breakfast', 'items' => 'Oats, fruits, nuts', 'portion' => '1 bowl'],
            ['time' => 'Lunch', 'items' => 'Rice, dal, vegetables', 'portion' => '1 plate'],
            ['time' => 'Snack', 'items' => 'Green tea, biscuits', 'portion' => '1 cup'],
            ['time' => 'Dinner', 'items' => 'Roti, curry, salad', 'portion' => '2 roti']
        ];
    }

    private function getDietGuidelines(User $user)
    {
        return [
            'Drink 8-10 glasses of water daily',
            'Include seasonal fruits and vegetables',
            'Avoid processed and junk food',
            'Eat meals at regular intervals'
        ];
    }

    private function getFitnessplan(User $user)
    {
        return [
            ['day' => 'Monday', 'name' => 'Walking', 'duration' => '30 min', 'sets' => '1 session'],
            ['day' => 'Tuesday', 'name' => 'Yoga', 'duration' => '20 min', 'sets' => '1 session'],
            ['day' => 'Wednesday', 'name' => 'Strength training', 'duration' => '25 min', 'sets' => '3 sets'],
            ['day' => 'Thursday', 'name' => 'Rest day', 'duration' => '-', 'sets' => '-']
        ];
    }

    private function getFitnessTips(User $user)
    {
        return [
            'Start slowly and gradually increase intensity',
            'Stay hydrated during workouts',
            'Get adequate rest between sessions',
            'Listen to your body and avoid overexertion'
        ];
    }
}