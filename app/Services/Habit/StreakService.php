<?php

namespace App\Services\Habit;

use App\Models\User;
use App\Models\UserStreak;

class StreakService
{
    public function update(User $user): UserStreak
    {
        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        $today     = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        if ($streak->last_checkin_date?->toDateString() === $today) {
            return $streak;
        }

        if ($streak->last_checkin_date?->toDateString() === $yesterday) {
            $streak->current_streak += 1;
        } else {
            $streak->current_streak = 1;
        }

        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_checkin_date = $today;
        $streak->save();

        return $streak;
    }

    public function reset(User $user): void
    {
        UserStreak::where('user_id', $user->id)
            ->update(['current_streak' => 0]);
    }
}
