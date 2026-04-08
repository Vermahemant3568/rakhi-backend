<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\DailyCheckin;
use App\Models\MealLog;
use App\Models\User;
use App\Services\Vector\UserMemoryService;

class ContextBuilder
{
    public function __construct(
        private UserMemoryService $memory
    ) {}

    public function build(
        User $user,
        string $currentMessage,
        int $sessionId,
        string $coachNamespace
    ): array {
        return [
            'recent_history' => $this->getRecentHistory($sessionId),

            'memories' => $this->memory->recall(
                $user,
                $currentMessage,
                limit: 5
            ),

            'knowledge' => $this->memory->recallCoachKnowledge(
                $coachNamespace,
                $currentMessage,
                limit: 3
            ),

            'checkin'     => $this->getTodayCheckin($user->id),
            'meals_today' => $this->getTodayMeals($user->id),
        ];
    }

    private function getRecentHistory(int $sessionId): array
    {
        return ChatMessage::where('session_id', $sessionId)
            ->latest()
            ->take(10)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role'    => $m->role,
                'message' => $m->message,
            ])
            ->values()
            ->toArray();
    }

    private function getTodayCheckin(int $userId): ?array
    {
        $checkin = DailyCheckin::where('user_id', $userId)
            ->where('checkin_date', today())
            ->first();

        return $checkin ? [
            'mood'         => $checkin->mood,
            'energy_level' => $checkin->energy_level,
            'sleep_hours'  => $checkin->sleep_hours,
            'water_intake' => $checkin->water_intake,
        ] : null;
    }

    private function getTodayMeals(int $userId): array
    {
        return MealLog::where('user_id', $userId)
            ->where('logged_date', today()->toDateString())
            ->get()
            ->map(fn($m) => [
                'meal'     => $m->meal_name,
                'time'     => $m->meal_time,
                'calories' => $m->calories,
            ])
            ->toArray();
    }
}
