<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DailyCheckin;
use App\Models\UserStreak;
use App\Services\Habit\StreakService;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private StreakService $streakService,
    ) {}

    // Daily checkin
    public function checkin(Request $request)
    {
        $request->validate([
            'mood'           => 'required|in:great,good,okay,low,bad',
            'energy_level'   => 'nullable|integer|min:1|max:10',
            'sleep_hours'    => 'nullable|numeric|min:0|max:24',
            'water_intake'   => 'nullable|numeric|min:0|max:10',
            'exercise_done'  => 'nullable|boolean',
            'notes'          => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $today = now()->toDateString();

        // Prevent duplicate checkins
        $existing = DailyCheckin::where('user_id', $user->id)
            ->where('checkin_date', $today)
            ->first();

        if ($existing) {
            $existing->update($request->only([
                'mood', 'energy_level', 'sleep_hours',
                'water_intake', 'exercise_done', 'notes',
            ]));
            $checkin = $existing;
        } else {
            $checkin = DailyCheckin::create([
                'user_id'       => $user->id,
                'mood'          => $request->mood,
                'energy_level'  => $request->energy_level,
                'sleep_hours'   => $request->sleep_hours,
                'water_intake'  => $request->water_intake,
                'exercise_done' => $request->exercise_done ?? false,
                'notes'         => $request->notes,
                'checkin_date'  => $today,
            ]);

            // Update streak
            $this->streakService->update($user);
        }

        return response()->json([
            'success' => true,
            'checkin' => $checkin,
            'streak'  => $user->fresh()->streak,
            'message' => $this->getMoodResponse($request->mood),
        ]);
    }

    // Get streak
    public function streak()
    {
        $user   = auth()->user();
        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        return response()->json([
            'success' => true,
            'streak'  => $streak,
        ]);
    }

    // Progress summary
    public function summary()
    {
        $user = auth()->user();

        $last7Days = DailyCheckin::where('user_id', $user->id)
            ->where('checkin_date', '>=', now()->subDays(7)->toDateString())
            ->orderBy('checkin_date', 'asc')
            ->get();

        $last30Days = DailyCheckin::where('user_id', $user->id)
            ->where('checkin_date', '>=', now()->subDays(30)->toDateString())
            ->get();

        return response()->json([
            'success'     => true,
            'streak'      => $user->streak,
            'last_7_days' => $last7Days,
            'averages'    => [
                'mood'         => $this->avgMood($last30Days),
                'energy'       => round($last30Days->avg('energy_level'), 1),
                'sleep'        => round($last30Days->avg('sleep_hours'), 1),
                'water'        => round($last30Days->avg('water_intake'), 1),
                'exercise_days'=> $last30Days->where('exercise_done', 1)->count(),
            ],
            'checkin_count' => $last30Days->count(),
        ]);
    }

    // Mood history
    public function moodHistory()
    {
        $checkins = DailyCheckin::where('user_id', auth()->id())
            ->orderBy('checkin_date', 'desc')
            ->take(30)
            ->get(['mood', 'energy_level', 'checkin_date']);

        return response()->json([
            'success'  => true,
            'history'  => $checkins,
        ]);
    }

    // ── Private Helpers ──────────────────────────

    private function getMoodResponse(string $mood): string
    {
        return match($mood) {
            'great' => "That's amazing! You're doing great today! 🌟",
            'good'  => "Great to hear you're feeling good! Keep it up! 😊",
            'okay'  => "Thanks for checking in. Let's make today even better! 💪",
            'low'   => "I hear you. It's okay to have tough days. " .
                       "I'm here for you. 💙",
            'bad'   => "I'm sorry you're feeling this way. " .
                       "You're not alone — I'm right here. 🌸",
            default => "Thanks for checking in! 😊",
        };
    }

    private function avgMood($checkins): string
    {
        $scores = [
            'great' => 5, 'good' => 4,
            'okay'  => 3, 'low'  => 2, 'bad' => 1
        ];

        if ($checkins->isEmpty()) return 'okay';

        $avg = $checkins->avg(fn($c) => $scores[$c->mood] ?? 3);

        return match(true) {
            $avg >= 4.5 => 'great',
            $avg >= 3.5 => 'good',
            $avg >= 2.5 => 'okay',
            $avg >= 1.5 => 'low',
            default     => 'bad',
        };
    }
}