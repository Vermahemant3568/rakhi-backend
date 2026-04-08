<?php

namespace App\Services\Habit;

use App\Models\DailyCheckin;
use App\Models\User;

class HabitScoringEngine
{
    public function score(User $user): array
    {
        $last30 = DailyCheckin::where('user_id', $user->id)
            ->where('checkin_date', '>=', now()->subDays(30)->toDateString())
            ->get();

        if ($last30->isEmpty()) {
            return ['total' => 0, 'breakdown' => [], 'grade' => 'Not enough data'];
        }

        $scores = [
            'consistency' => $this->consistencyScore($last30->count()),
            'sleep'       => $this->sleepScore($last30),
            'water'       => $this->waterScore($last30),
            'exercise'    => $this->exerciseScore($last30),
            'mood'        => $this->moodScore($last30),
        ];

        $total = round(array_sum($scores) / count($scores));

        return [
            'total'     => $total,
            'breakdown' => $scores,
            'grade'     => $this->getGrade($total),
        ];
    }

    private function consistencyScore(int $checkins): int
    {
        return min(100, round(($checkins / 30) * 100));
    }

    private function sleepScore($checkins): int
    {
        $avg = $checkins->whereNotNull('sleep_hours')->avg('sleep_hours') ?? 0;

        if ($avg >= 7 && $avg <= 9) return 100;
        if ($avg >= 6)              return 75;
        if ($avg >= 5)              return 50;
        return 25;
    }

    private function waterScore($checkins): int
    {
        $avg = $checkins->whereNotNull('water_intake')->avg('water_intake') ?? 0;

        if ($avg >= 2.5) return 100;
        if ($avg >= 2.0) return 75;
        if ($avg >= 1.5) return 50;
        return 25;
    }

    private function exerciseScore($checkins): int
    {
        return min(100, round(($checkins->where('exercise_done', true)->count() / 20) * 100));
    }

    private function moodScore($checkins): int
    {
        $moodMap = ['great' => 100, 'good' => 75, 'okay' => 50, 'low' => 25, 'bad' => 0];

        $avg = $checkins->avg(fn($c) => $moodMap[$c->mood] ?? 50);

        return round($avg ?? 50);
    }

    private function getGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => '🌟 Excellent',
            $score >= 75 => '💪 Great',
            $score >= 60 => '👍 Good',
            $score >= 45 => '📈 Improving',
            default      => '🌱 Just Starting',
        };
    }
}
