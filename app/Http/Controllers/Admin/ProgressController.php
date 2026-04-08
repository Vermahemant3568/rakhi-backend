<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyCheckin;
use App\Models\UserPlan;
use App\Models\UserStreak;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ProgressController extends Controller
{
    // GET /admin/progress/overview
    public function overview(): JsonResponse
    {
        $today = now()->toDateString();
        $last30 = now()->subDays(30)->toDateString();

        return response()->json([
            'checkins_today'        => DailyCheckin::where('checkin_date', $today)->count(),
            'checkins_last_30_days' => DailyCheckin::where('checkin_date', '>=', $last30)->count(),
            'active_streaks'        => UserStreak::where('current_streak', '>', 0)->count(),
            'longest_streak_ever'   => UserStreak::max('longest_streak') ?? 0,
            'avg_sleep'             => round(DailyCheckin::where('checkin_date', '>=', $last30)->avg('sleep_hours'), 1),
            'avg_water'             => round(DailyCheckin::where('checkin_date', '>=', $last30)->avg('water_intake'), 1),
            'exercise_days_total'   => DailyCheckin::where('checkin_date', '>=', $last30)->where('exercise_done', 1)->count(),
            'plans_generated'       => UserPlan::where('generated_at', '>=', $last30)->count(),
        ]);
    }

    // GET /admin/progress/streaks
    public function streaks(): JsonResponse
    {
        $streaks = UserStreak::with('user:id,first_name,last_name,email')
            ->orderByDesc('current_streak')
            ->get(['user_id', 'current_streak', 'longest_streak', 'last_checkin_date']);

        return response()->json(['streaks' => $streaks]);
    }

    // GET /admin/progress/summary
    public function summary(): JsonResponse
    {
        $last30 = now()->subDays(30)->toDateString();

        $users = User::where('is_active', 1)
            ->whereHas('dailyCheckins', fn($q) => $q->where('checkin_date', '>=', $last30))
            ->with(['dailyCheckins' => fn($q) => $q->where('checkin_date', '>=', $last30)])
            ->get(['id', 'first_name', 'last_name', 'email']);

        $summary = $users->map(function ($user) {
            $checkins = $user->dailyCheckins;
            return [
                'user'          => $user->only('id', 'first_name', 'last_name', 'email'),
                'checkin_count' => $checkins->count(),
                'avg_sleep'     => round($checkins->avg('sleep_hours'), 1),
                'avg_water'     => round($checkins->avg('water_intake'), 1),
                'avg_energy'    => round($checkins->avg('energy_level'), 1),
                'exercise_days' => $checkins->where('exercise_done', true)->count(),
            ];
        });

        return response()->json(['summary' => $summary]);
    }

    // GET /admin/progress/chat-activity
    public function chatActivity(): JsonResponse
    {
        $messages = ChatMessage::where('role', 'rakhi')
            ->where('message', 'like', "%Diet Plan%")
            ->orWhere('message', 'like', "%Fitness Plan%")
            ->with([
                'session:id,user_id,coach_id',
                'session.user:id,first_name,last_name,email',
            ])
            ->orderByDesc('created_at')
            ->take(100)
            ->get(['id', 'session_id', 'message', 'created_at']);

        $result = $messages->map(function ($msg) {
            $userId = $msg->session?->user_id;
            $plan   = $userId
                ? \App\Models\UserPlan::where('user_id', $userId)
                    ->latest('generated_at')
                    ->first(['plan_type', 'file_url', 'generated_at'])
                : null;

            return [
                'message_id'  => $msg->id,
                'user'        => $msg->session?->user?->only('id', 'first_name', 'last_name', 'email'),
                'triggered_at'=> $msg->created_at,
                'plan_status' => $plan ? 'generated' : 'pending',
                'plan'        => $plan,
            ];
        });

        return response()->json(['chat_activity' => $result]);
    }
}
