<?php

namespace App\Services\Coach;

use App\Models\Coach;
use App\Models\Goal;
use App\Models\User;
use App\Models\UserCoach;

class CoachRouter
{
    private array $goalCoachMap = [
        'diabetes'          => 'diabetes-coach',
        'weight-loss'       => 'weight-loss-coach',
        'pcos-pcod'         => 'pcos-thyroid-coach',
        'thyroid'           => 'pcos-thyroid-coach',
        'irregular-periods' => 'pcos-thyroid-coach',
        'fitness'           => 'fitness-coach',
        'diet'              => 'diet-nutrition-coach',
        'mental-wellness'   => 'mental-wellness-coach',
        'stress'            => 'stress-coach',
        'sleep'             => 'sleep-coach',
        'energy'            => 'energy-coach',
        'pregnancy'         => 'pregnancy-coach',
        'postpartum'        => 'postpartum-coach',
        'habit'             => 'habit-coach',
        'vision'            => 'vision-coach',
    ];

    // Route messages to the right coach based on keywords
    private array $messageCoachMap = [
        'diabetes-coach'        => [
            'diabetes', 'blood sugar', 'sugar level', 'insulin', 'hba1c', 'glucose',
            'type 1', 'type 2', 'diabetic', 'sugar', 'madhumeh',
        ],
        'diet-nutrition-coach'  => [
            'diet', 'nutrition', 'calories', 'protein', 'carbs', 'fat', 'vitamin',
            'mineral', 'supplement', 'khana', 'khaana', 'food', 'eat', 'meal',
            'recipe', 'healthy eating', 'balanced diet', 'nutrients',
        ],
        'fitness-coach'         => [
            'workout', 'exercise', 'gym', 'run', 'walk', 'yoga', 'strength',
            'cardio', 'steps', 'active', 'training', 'vyayam', 'kasrat',
            'push up', 'squat', 'stretching', 'physical activity',
        ],
        'pcos-thyroid-coach'    => [
            'pcos', 'pcod', 'thyroid', 'hypothyroid', 'hyperthyroid', 'hormones',
            'irregular period', 'periods', 'menstrual', 'ovary', 'tsh', 't3', 't4',
            'hair fall', 'hair loss', 'weight gain pcos', 'facial hair', 'acne pcos',
        ],
        'mental-wellness-coach' => [
            'anxiety', 'depression', 'mental health', 'panic', 'overthinking',
            'negative thoughts', 'therapy', 'counselling', 'emotional', 'mindset',
            'confidence', 'self esteem', 'mental', 'psychology',
        ],
        'sleep-coach'           => [
            'sleep', 'insomnia', 'neend', 'so nahi', 'waking up', 'tired morning',
            'sleep quality', 'deep sleep', 'sleep schedule', 'bedtime', 'night routine',
            'oversleeping', 'sleep cycle',
        ],
        'weight-loss-coach'     => [
            'weight loss', 'lose weight', 'fat loss', 'slim', 'obesity', 'bmi',
            'belly fat', 'waist', 'vajan', 'mota', 'weight kam', 'weight ghata',
            'calorie deficit', 'intermittent fasting',
        ],
        'pregnancy-coach'       => [
            'pregnant', 'pregnancy', 'trimester', 'prenatal', 'garbh', 'baby bump',
            'morning sickness', 'folic acid', 'delivery', 'labour', 'antenatal',
        ],
        'postpartum-coach'      => [
            'postpartum', 'after delivery', 'new mom', 'breastfeeding', 'nursing',
            'baby weight', 'postnatal', 'c section recovery', 'new mother',
        ],
        'energy-coach'          => [
            'energy', 'fatigue', 'tired', 'exhausted', 'low energy', 'thaka',
            'lethargy', 'sluggish', 'no motivation', 'always tired', 'weakness',
            'stamina', 'vitality',
        ],
        'stress-coach'          => [
            'stress', 'tension', 'pressure', 'burnout', 'overwhelmed', 'anxious',
            'worried', 'relax', 'calm', 'meditation', 'breathing', 'mindfulness',
            'pareshan', 'ghabra', 'takleef',
        ],
        'habit-coach'           => [
            'habit', 'routine', 'consistency', 'discipline', 'daily routine',
            'morning routine', 'night routine', 'goal setting', 'productivity',
            'aadat', 'niyam', 'schedule', 'streak',
        ],
        'vision-coach'          => [
            'eyes', 'vision', 'eyesight', 'spectacles', 'glasses', 'screen time',
            'eye strain', 'dry eyes', 'eye health', 'aankh', 'nazar', 'chasma',
        ],
    ];

    public function assignCoaches(User $user, array $goalIds): void
    {
        UserCoach::where('user_id', $user->id)->delete();

        $goals = Goal::whereIn('id', $goalIds)->get();
        $assignedCoachSlugs = [];

        foreach ($goals as $goal) {
            $coachSlug = $this->goalCoachMap[$goal->slug] ?? 'diet-nutrition-coach';
            if (!in_array($coachSlug, $assignedCoachSlugs)) {
                $assignedCoachSlugs[] = $coachSlug;
            }
        }

        if (!in_array('habit-coach', $assignedCoachSlugs)) {
            $assignedCoachSlugs[] = 'habit-coach';
        }

        $isPrimary = true;

        foreach ($assignedCoachSlugs as $slug) {
            $coach = Coach::where('slug', $slug)->where('is_active', 1)->first();
            if ($coach) {
                UserCoach::create([
                    'user_id'    => $user->id,
                    'coach_id'   => $coach->id,
                    'is_primary' => $isPrimary ? 1 : 0,
                ]);
                $isPrimary = false;
            }
        }
    }

    /**
     * Single source of truth for slug → coach service class.
     * Used by both ChatController and VoiceController.
     */
    public function resolveCoachClass(string $slug): string
    {
        return match($slug) {
            'diabetes-coach'        => \App\Services\Coach\DiabetesCoach::class,
            'diet-nutrition-coach'  => \App\Services\Coach\DietNutritionCoach::class,
            'fitness-coach'         => \App\Services\Coach\FitnessCoach::class,
            'pcos-thyroid-coach'    => \App\Services\Coach\PCOSThyroidCoach::class,
            'mental-wellness-coach' => \App\Services\Coach\MentalWellnessCoach::class,
            'sleep-coach'           => \App\Services\Coach\SleepCoach::class,
            'weight-loss-coach'     => \App\Services\Coach\WeightLossCoach::class,
            'pregnancy-coach'       => \App\Services\Coach\PregnancyCoach::class,
            'postpartum-coach'      => \App\Services\Coach\PostpartumCoach::class,
            'energy-coach'          => \App\Services\Coach\EnergyCoach::class,
            'stress-coach'          => \App\Services\Coach\StressCoach::class,
            'habit-coach'           => \App\Services\Coach\HabitCoach::class,
            'vision-coach'          => \App\Services\Coach\VisionCoach::class,
            default                 => \App\Services\Coach\DietNutritionCoach::class,
        };
    }

    /**
     * Resolve and instantiate the correct coach service for a given slug.
     */
    public function resolveCoachService(string $slug): object
    {
        return app($this->resolveCoachClass($slug));
    }

    public function resolveCoach(User $user, string $message): Coach
    {
        // Try to match message to a specific coach by keywords
        $messageLower = strtolower($message);

        foreach ($this->messageCoachMap as $coachSlug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    $coach = Coach::where('slug', $coachSlug)->where('is_active', 1)->first();
                    if ($coach) return $coach;
                }
            }
        }

        // Fall back to user's primary coach
        $primaryCoach = $user->primaryCoach();
        if ($primaryCoach) return $primaryCoach;

        // Last resort fallback
        return Coach::where('slug', 'diet-nutrition-coach')->where('is_active', 1)->first()
            ?? Coach::where('is_active', 1)->first()
            ?? Coach::first();
    }
}
