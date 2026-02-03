<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index');
    }
    
    public function data(Request $request)
    {
        $query = User::with(['profile', 'subscription', 'goals']);
        
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('mobile', 'like', "%{$search}%")
                  ->orWhere('country_code', 'like', "%{$search}%")
                  ->orWhereHas('profile', function($profileQuery) use ($search) {
                      $profileQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }
        
        $users = $query->latest()->get()->map(function($user) {
            $fullName = 'N/A';
            if ($user->profile) {
                $fullName = trim($user->profile->first_name . ' ' . ($user->profile->last_name ?? ''));
            }
            
            // Get goals with both ID and title from user_goals relationship
            $goals = $user->goals->map(function($goal) {
                return [
                    'id' => $goal->id,
                    'title' => $goal->title
                ];
            })->toArray();
            
            return [
                'id' => $user->id,
                'name' => $fullName,
                'email' => 'N/A', // No email field in current structure
                'phone' => $user->country_code . $user->mobile,
                'gender' => $user->profile?->gender ?? 'N/A',
                'age' => $user->profile && $user->profile->dob ? now()->diffInYears($user->profile->dob) : null,
                'goals' => $goals,
                'is_active' => $user->is_active,
                'is_onboarded' => $user->is_onboarded ?? false,
                'mobile_verified' => $user->mobile_verified_at ? true : false,
                'subscription_status' => $user->subscription?->status ?? 'none',
                'subscription_period' => $user->subscription ? (
                    $user->subscription->current_period_start && $user->subscription->current_period_end 
                    ? $user->subscription->current_period_start . ' to ' . $user->subscription->current_period_end
                    : ($user->subscription->trial_start && $user->subscription->trial_end 
                        ? 'Trial: ' . $user->subscription->trial_start . ' to ' . $user->subscription->trial_end
                        : 'N/A')
                ) : 'N/A',
                'created_at' => $user->created_at
            ];
        });
        
        return response()->json($users);
    }
    
    public function toggle($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        
        return response()->json(['success' => true]);
    }
    
    public function activityLogs($id)
    {
        $user = User::findOrFail($id);
        $logs = UserActivityLog::where('user_id', $id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(function($log) {
                return [
                    'event' => $log->event,
                    'meta' => $log->meta,
                    'created_at' => $log->created_at->format('M d, Y H:i:s')
                ];
            });
        
        return response()->json($logs);
    }
}