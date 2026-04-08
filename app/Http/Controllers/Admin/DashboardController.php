<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Coach;
use App\Models\ChatSession;
use App\Models\UserSubscription;
use App\Models\DailyCheckin;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'users' => [
                'total'   => User::count(),
                'active'  => User::where('is_active', 1)->count(),
                'banned'  => User::where('is_banned', 1)->count(),
                'today'   => User::whereDate('created_at', today())->count(),
            ],
            'subscriptions' => [
                'trial'     => UserSubscription::where('status', 'trial')->count(),
                'active'    => UserSubscription::where('status', 'active')->count(),
                'expired'   => UserSubscription::where('status', 'expired')->count(),
                'cancelled' => UserSubscription::where('status', 'cancelled')->count(),
            ],
            'chats' => [
                'total_sessions'  => ChatSession::count(),
                'active_sessions' => ChatSession::where('status', 'active')->count(),
                'today_sessions'  => ChatSession::whereDate('started_at', today())->count(),
            ],
            'checkins' => [
                'today' => DailyCheckin::whereDate('checkin_date', today())->count(),
                'total' => DailyCheckin::count(),
            ],
            'coaches' => [
                'total'  => Coach::count(),
                'active' => Coach::where('is_active', 1)->count(),
            ],
        ]);
    }
}
