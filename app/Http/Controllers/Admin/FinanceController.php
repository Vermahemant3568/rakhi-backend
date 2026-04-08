<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;

class FinanceController extends Controller
{
    public function index()
    {
        return response()->json([
            'revenue' => [
                'total'      => UserSubscription::where('status', 'active')->sum('amount_paid'),
                'this_month' => UserSubscription::where('status', 'active')
                                    ->whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->sum('amount_paid'),
                'last_month' => UserSubscription::where('status', 'active')
                                    ->whereMonth('created_at', now()->subMonth()->month)
                                    ->whereYear('created_at', now()->subMonth()->year)
                                    ->sum('amount_paid'),
                'today'      => UserSubscription::where('status', 'active')
                                    ->whereDate('created_at', today())
                                    ->sum('amount_paid'),
            ],
            'subscriptions' => [
                'active'    => UserSubscription::where('status', 'active')->count(),
                'trial'     => UserSubscription::where('status', 'trial')->count(),
                'expired'   => UserSubscription::where('status', 'expired')->count(),
                'cancelled' => UserSubscription::where('status', 'cancelled')->count(),
            ],
            'plan_breakdown' => SubscriptionPlan::withCount('subscriptions')
                                    ->withSum('subscriptions', 'amount_paid')
                                    ->get()
                                    ->map(fn($p) => [
                                        'plan_name' => $p->name,
                                        'revenue'   => $p->subscriptions_sum_amount_paid ?? 0,
                                        'count'     => $p->subscriptions_count ?? 0,
                                    ]),
            'recent_subscriptions' => UserSubscription::with(['user', 'plan'])
                                            ->latest()
                                            ->take(20)
                                            ->get(),
        ]);
    }
}
