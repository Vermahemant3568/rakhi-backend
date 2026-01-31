<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * App entry status check
     * Decides: Onboarding | Payment | Rakhi
     */
    public function appStatus(Request $request)
    {
        $user = $request->user();

        $subscription = UserSubscription::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'is_onboarded' => $user->is_onboarded,
                'subscription_status' => $subscription?->status,
                'trial_end' => $subscription?->trial_end,
                'current_period_end' => $subscription?->current_period_end
            ]
        ]);
    }

    /**
     * Create trial AFTER onboarding + payment success
     */
    public function startTrial(Request $request)
    {
        $user = $request->user();

        // prevent duplicate trial
        if (UserSubscription::where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription already exists'
            ], 409);
        }

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'status' => 'trial',
            'trial_start' => Carbon::now(),
            'trial_end' => Carbon::now()->addDays(7),
            'payment_provider' => 'razorpay'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trial started',
            'data' => $subscription
        ]);
    }
}
