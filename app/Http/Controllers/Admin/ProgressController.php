<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DailyCheckin;
use App\Models\User;
use App\Models\UserPlan;
use App\Models\UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    // GET /admin/progress/overview
    public function overview(): JsonResponse
    {
        $today  = now()->toDateString();
        $last30 = now()->subDays(30)->toDateString();

        $streaks    = UserStreak::where('current_streak', '>', 0)->get(['current_streak']);
        $avgStreak  = $streaks->avg('current_streak') ?? 0;

        return response()->json([
            // Checkins
            'checkins_today'        => DailyCheckin::where('checkin_date', $today)->count(),
            'checkins_last_30_days' => DailyCheckin::where('checkin_date', '>=', $last30)->count(),
            'total_checkins'        => DailyCheckin::count(),

            // Streaks
            'active_streaks'        => $streaks->count(),
            'longest_streak_ever'   => UserStreak::max('longest_streak') ?? 0,
            'avg_streak'            => round($avgStreak, 1),

            // Health metrics (last 30 days)
            'avg_sleep'             => round(DailyCheckin::where('checkin_date', '>=', $last30)->avg('sleep_hours') ?? 0, 1),
            'avg_water'             => round(DailyCheckin::where('checkin_date', '>=', $last30)->avg('water_intake') ?? 0, 1),
            'exercise_days_total'   => DailyCheckin::where('checkin_date', '>=', $last30)->where('exercise_done', 1)->count(),

            // Plans
            'plans_generated'       => UserPlan::where('generated_at', '>=', $last30)->count(),
            'pdfs_generated'        => UserPlan::whereNotNull('file_url')->count(),
            'diet_plans_today'      => UserPlan::where('plan_type', 'diet')
                                            ->whereDate('generated_at', $today)->count(),
            'fitness_plans_today'   => UserPlan::where('plan_type', 'fitness')
                                            ->whereDate('generated_at', $today)->count(),

            // Chat
            'total_chats'           => ChatMessage::where('role', 'user')->count(),

            // Plan states
            'plans_generating'      => User::where('plan_generation_state', 'generating')->count(),
            'plans_failed'          => User::where('plan_generation_state', 'failed')->count(),
            'plans_completed'       => User::where('plan_generation_state', 'completed')->count(),
        ]);
    }

    // GET /admin/progress/streaks
    public function streaks(): JsonResponse
    {
        $streaks = UserStreak::with('user:id,first_name,last_name,mobile,email')
            ->orderByDesc('current_streak')
            ->get()
            ->map(function ($s) {
                return [
                    'user_id'        => $s->user_id,
                    'user'           => $s->user,
                    'current_streak' => $s->current_streak ?? 0,
                    'longest_streak' => $s->longest_streak ?? 0,
                    'total_checkins' => DailyCheckin::where('user_id', $s->user_id)->count(),
                    'last_checkin_at'=> $s->last_checkin_date,
                ];
            });

        return response()->json(['streaks' => $streaks]);
    }

    // GET /admin/progress/summary
    public function summary(): JsonResponse
    {
        $last30 = now()->subDays(30)->toDateString();

        $users = User::where('is_active', 1)
            ->whereHas('dailyCheckins', fn($q) => $q->where('checkin_date', '>=', $last30))
            ->with(['dailyCheckins' => fn($q) => $q->where('checkin_date', '>=', $last30)])
            ->get(['id', 'first_name', 'last_name', 'mobile', 'email']);

        $summary = $users->map(function ($user) {
            $checkins = $user->dailyCheckins;
            return [
                'user'          => $user->only('id', 'first_name', 'last_name', 'mobile', 'email'),
                'checkin_days'  => $checkins->count(),
                'avg_sleep'     => round((float) ($checkins->avg('sleep_hours') ?? 0), 1),
                'avg_water'     => round((float) ($checkins->avg('water_intake') ?? 0), 1),
                'avg_energy'    => null,
                'avg_mood'      => null,
                'exercise_days' => $checkins->where('exercise_done', true)->count(),
                'avg_calories'  => null,
                'avg_steps'     => null,
            ];
        });

        return response()->json(['summary' => $summary]);
    }

    // GET /admin/progress/chat-activity
    // Returns plan generation events with user, plan type, PDF status, and job state
    public function chatActivity(): JsonResponse
    {
        // Get users who have had plan generation activity
        $users = User::whereNotNull('plan_generation_state')
            ->with([
                'userPlans' => fn($q) => $q->orderByDesc('generated_at'),
                'chatSessions' => fn($q) => $q->where('session_type', 'chat')->latest('id')->limit(1),
            ])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'first_name', 'last_name', 'mobile', 'consultation_state', 'plan_generation_state', 'updated_at']);

        $result = $users->map(function ($user) {
            $plans = $user->userPlans->groupBy('plan_type');

            $consultationPlan = $plans->get('consultation')?->first();
            $dietPlan         = $plans->get('diet')?->first();
            $fitnessPlan      = $plans->get('fitness')?->first();

            $session = $user->chatSessions->first();

            return [
                'user_id'               => $user->id,
                'user'                  => $user->only('id', 'first_name', 'last_name', 'mobile'),
                'consultation_state'    => $user->consultation_state,
                'plan_generation_state' => $user->plan_generation_state,
                'job_status'            => $this->resolveJobStatus($user->plan_generation_state),
                'session_id'            => $session?->id,
                'plans' => [
                    'consultation' => $consultationPlan ? [
                        'id'           => $consultationPlan->id,
                        'version'      => $consultationPlan->version,
                        'file_url'     => $consultationPlan->file_url,
                        'generated_at' => $consultationPlan->generated_at,
                    ] : null,
                    'diet' => $dietPlan ? [
                        'id'           => $dietPlan->id,
                        'version'      => $dietPlan->version,
                        'file_url'     => $dietPlan->file_url,
                        'generated_at' => $dietPlan->generated_at,
                    ] : null,
                    'fitness' => $fitnessPlan ? [
                        'id'           => $fitnessPlan->id,
                        'version'      => $fitnessPlan->version,
                        'file_url'     => $fitnessPlan->file_url,
                        'generated_at' => $fitnessPlan->generated_at,
                    ] : null,
                ],
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json(['chat_activity' => $result]);
    }

    // GET /admin/progress/stuck-plans
    // Users stuck in generating state with no active queue job
    public function stuckPlans(): JsonResponse
    {
        $cutoff = now()->subMinutes(15);

        $stuck = User::where('plan_generation_state', 'generating')
            ->where('updated_at', '<', $cutoff)
            ->get(['id', 'first_name', 'last_name', 'mobile', 'plan_generation_state', 'consultation_state', 'updated_at'])
            ->map(function ($user) {
                $hasActiveJob = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('payload', 'like', '%"user_id":' . $user->id . '%')
                    ->exists();

                return [
                    'user_id'               => $user->id,
                    'user'                  => $user->only('id', 'first_name', 'last_name', 'mobile'),
                    'plan_generation_state' => $user->plan_generation_state,
                    'consultation_state'    => $user->consultation_state,
                    'stuck_since'           => $user->updated_at,
                    'has_active_job'        => $hasActiveJob,
                    'minutes_stuck'         => now()->diffInMinutes($user->updated_at),
                ];
            });

        return response()->json(['stuck_plans' => $stuck]);
    }

    private function resolveJobStatus(?string $planState): string
    {
        return match($planState) {
            'generating' => 'processing',
            'completed'  => 'completed',
            'failed'     => 'failed',
            default      => 'pending',
        };
    }
}
