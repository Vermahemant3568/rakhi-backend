<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Models\DailyCheckin;
use App\Models\AiEventLog;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function dashboardData()
    {
        try {
            return response()->json([
                'cards' => [
                    'total_users' => User::count() ?: 0,
                    'active_today' => UserActivityLog::whereDate('created_at', today())->distinct('user_id')->count('user_id') ?: 0,
                    'active_subscribers' => UserSubscription::where('status', 'active')->count() ?: 0,
                    'trial_users' => UserSubscription::where('status', 'trial')->count() ?: 0,
                    'monthly_revenue' => (PaymentTransaction::where('status', 'success')->whereMonth('created_at', now()->month)->sum('amount') ?: 0) / 100
                ],
                'charts' => [
                    'dau_wau' => $this->getDauWau(),
                    'mood_trend' => DailyCheckin::select('mood', DB::raw('count(*) as total'))->whereNotNull('mood')->groupBy('mood')->get() ?: [],
                    'chat_voice_usage' => UserActivityLog::select('event', DB::raw('count(*) as total'))->whereIn('event', ['chat_message', 'voice_call'])->groupBy('event')->get() ?: [],
                    'intent_distribution' => AiEventLog::select('intent', DB::raw('count(*) as total'))->groupBy('intent')->limit(10)->get() ?: [],
                    'safety_triggers' => AiEventLog::where('safety_triggered', true)->count() ?: 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'cards' => [
                    'total_users' => 0,
                    'active_today' => 0,
                    'active_subscribers' => 0,
                    'trial_users' => 0,
                    'monthly_revenue' => 0
                ],
                'charts' => [
                    'dau_wau' => ['dau' => [], 'wau' => []],
                    'mood_trend' => [],
                    'chat_voice_usage' => [],
                    'intent_distribution' => [],
                    'safety_triggers' => 0
                ]
            ]);
        }
    }
    
    private function getDauWau()
    {
        $dau = [];
        $wau = [];
        
        try {
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dau[] = [
                    'date' => $date->format('M d'),
                    'count' => UserActivityLog::whereDate('created_at', $date)->distinct('user_id')->count('user_id') ?: 0
                ];
            }
            
            for ($i = 3; $i >= 0; $i--) {
                $startDate = Carbon::today()->subWeeks($i)->startOfWeek();
                $endDate = Carbon::today()->subWeeks($i)->endOfWeek();
                $wau[] = [
                    'week' => 'Week ' . ($i + 1),
                    'count' => UserActivityLog::whereBetween('created_at', [$startDate, $endDate])->distinct('user_id')->count('user_id') ?: 0
                ];
            }
        } catch (\Exception $e) {
            // Return empty arrays if there's an error
        }
        
        return ['dau' => $dau, 'wau' => $wau];
    }
    
    public function userEngagement()
    {
        $engagement = UserActivityLog::select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->get();
        
        return response()->json($engagement);
    }
    
    public function dailyActiveUsers()
    {
        $count = UserActivityLog::whereDate('created_at', today())
            ->distinct('user_id')
            ->count('user_id');
        
        return response()->json(['daily_active_users' => $count]);
    }
    
    public function moodTrend()
    {
        $mood = DailyCheckin::select('mood', DB::raw('count(*) as total'))
            ->groupBy('mood')
            ->get();
        
        return response()->json($mood);
    }
    
    public function safetyAlerts()
    {
        $count = AiEventLog::where('safety_triggered', true)->count();
        
        return response()->json(['safety_alerts' => $count]);
    }
}
