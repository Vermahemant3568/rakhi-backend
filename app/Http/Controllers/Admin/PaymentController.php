<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use DB;

class PaymentController extends Controller
{
    public function index()
    {
        return view('admin.payments.index');
    }
    
    public function transactionsStats()
    {
        $totalRevenue = PaymentTransaction::where('status', 'success')->sum('amount');
        $trialRevenue = PaymentTransaction::where('type', 'trial')->where('status', 'success')->sum('amount');
        $subscriptionRevenue = PaymentTransaction::where('type', 'subscription')->where('status', 'success')->sum('amount');
        
        $stats = [
            'total_revenue' => $totalRevenue,
            'trial_revenue' => $trialRevenue,
            'subscription_revenue' => $subscriptionRevenue,
            'total_transactions' => PaymentTransaction::count(),
            'active_subscribers' => UserSubscription::where('status','active')->count(),
            'trial_users' => UserSubscription::where('status','trial')->count(),
            'today_revenue' => PaymentTransaction::where('status', 'success')->whereDate('created_at', today())->sum('amount'),
            'monthly_revenue' => PaymentTransaction::where('status', 'success')->whereMonth('created_at', now()->month)->sum('amount'),
            'trial_percentage' => $totalRevenue > 0 ? round(($trialRevenue / $totalRevenue) * 100, 1) : 0,
            'subscription_percentage' => $totalRevenue > 0 ? round(($subscriptionRevenue / $totalRevenue) * 100, 1) : 0,
        ];
        
        return response()->json($stats);
    }
    
    public function transactionsData(Request $request)
    {
        $query = PaymentTransaction::with(['user.profile']);
        
        if ($request->id) {
            $query->where('id', $request->id);
        }
        
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('provider_payment_id', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('mobile', 'like', "%{$search}%");
                  });
            });
        }
        
        $transactions = $query->latest()->get()->map(function($transaction) {
            return [
                'id' => $transaction->id,
                'user_mobile' => $transaction->user ? ($transaction->user->country_code . $transaction->user->mobile) : 'N/A',
                'user_name' => $transaction->user && $transaction->user->profile 
                    ? trim($transaction->user->profile->first_name . ' ' . ($transaction->user->profile->last_name ?? ''))
                    : 'N/A',
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency ?? 'INR',
                'status' => $transaction->status,
                'payment_provider' => $transaction->payment_provider,
                'provider_payment_id' => $transaction->provider_payment_id,
                'provider_subscription_id' => $transaction->provider_subscription_id,
                'created_at' => $transaction->created_at
            ];
        });
        
        return response()->json($transactions);
    }

    public function revenueSummary()
    {
        return view('admin.payments.revenue');
    }
    
    public function revenueData()
    {
        $trialPrice = (float) \App\Models\SystemSetting::get('trial_price', 8);
        $subscriptionPrice = (float) \App\Models\SystemSetting::get('monthly_price', 299);
        
        $data = [
            'trial_revenue' => PaymentTransaction::where('type','trial')->where('status', 'success')->sum('amount'),
            'subscription_revenue' => PaymentTransaction::where('type','subscription')->where('status', 'success')->sum('amount'),
            'total_revenue' => PaymentTransaction::where('status', 'success')->sum('amount'),
            'active_subscribers' => UserSubscription::where('status','active')->count(),
            'trial_users' => UserSubscription::where('status','trial')->count(),
            'monthly_revenue' => PaymentTransaction::where('status', 'success')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
            'today_revenue' => PaymentTransaction::where('status', 'success')
                ->whereDate('created_at', today())
                ->sum('amount'),
            'trial_price' => $trialPrice,
            'subscription_price' => $subscriptionPrice,
        ];
        
        return response()->json($data);
    }

    public function activeSubscribers()
    {
        return UserSubscription::where('status','active')->count();
    }

    public function trialUsers()
    {
        return UserSubscription::where('status','trial')->count();
    }
}