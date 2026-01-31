<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use DB;

class PaymentController extends Controller
{
    // 1️⃣ All payments list
    public function transactions()
    {
        $transactions = PaymentTransaction::with('user')
            ->orderBy('id', 'desc')
            ->paginate(20);
            
        return view('admin.payments.index', compact('transactions'));
    }

    // 2️⃣ Revenue summary
    public function revenueSummary()
    {
        $data = [
            'trial_revenue' => PaymentTransaction::where('type','trial')->sum('amount'),
            'subscription_revenue' => PaymentTransaction::where('type','subscription')->sum('amount'),
            'total_revenue' => PaymentTransaction::sum('amount'),
            'active_subscribers' => UserSubscription::where('status','active')->count(),
            'trial_users' => UserSubscription::where('status','trial')->count(),
        ];
        
        return view('admin.payments.revenue', compact('data'));
    }

    // 3️⃣ Active subscribers
    public function activeSubscribers()
    {
        return UserSubscription::where('status','active')->count();
    }

    // 4️⃣ Trial users
    public function trialUsers()
    {
        return UserSubscription::where('status','trial')->count();
    }
}