<?php

namespace App\Services\Analytics;

use App\Models\DailyCheckin;
use Carbon\Carbon;

class StreakService
{
    public function currentStreak(int $userId): int
    {
        $streak = 0;
        $date = Carbon::today();

        while (DailyCheckin::where('user_id',$userId)
            ->where('date',$date->toDateString())
            ->exists()) {

            $streak++;
            $date->subDay();
        }

        return $streak;
    }
}