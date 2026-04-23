<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Payment\RazorpayService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private RazorpayService $razorpay
    ) {}

    // Screen 15 — Get plans
    public function plans()
    {
        return response()->json([
            'success' => true,
            'plans'   => SubscriptionPlan::where('is_active', 1)
                            ->orderBy('sort_order')
                            ->get(),
        ]);
    }

    // Screen 13 — Start free trial
    public function startTrial()
    {
        $user = auth()->user();

        // Check if already had trial
        $existing = UserSubscription::where('user_id', $user->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Trial already used'
            ], 422);
        }

        $plan = SubscriptionPlan::where('is_active', 1)
                                ->first();

        $trialDays = config('rakhi.trial_days', 7);

        UserSubscription::create([
            'user_id'         => $user->id,
            'plan_id'         => $plan->id,
            'status'          => 'trial',
            'trial_starts_at' => now(),
            'trial_ends_at'   => now()->addDays($trialDays),
            'ends_at'         => now()->addDays($trialDays),
        ]);

        return response()->json([
            'success'        => true,
            'message'        => 'Trial started! Enjoy ' . $trialDays . ' days free.',
            'trial_ends_at'  => now()->addDays($trialDays),
        ]);
    }

    // Screen 15 — Create Razorpay order
    public function createOrder(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $plan  = SubscriptionPlan::findOrFail($request->plan_id);
        $order = $this->razorpay->createOrder($plan->price);

        return response()->json([
            'success'  => true,
            'order'    => $order,
            'plan'     => $plan,
            'key_id'   => $this->razorpay->getKeyId(),
        ]);
    }

    // Screen 16 — Verify payment + activate
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'plan_id'              => 'required|exists:subscription_plans,id',
            'razorpay_order_id'    => 'required|string',
            'razorpay_payment_id'  => 'required|string',
            'razorpay_signature'   => 'required|string',
        ]);

        // Verify signature
        $valid = $this->razorpay->verifySignature(
            $request->razorpay_order_id,
            $request->razorpay_payment_id,
            $request->razorpay_signature
        );

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 422);
        }

        $user = auth()->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Update or create subscription
        UserSubscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id'              => $plan->id,
                'status'               => 'active',
                'starts_at'            => now(),
                'ends_at'              => now()->addDays($plan->duration_days),
                'razorpay_order_id'    => $request->razorpay_order_id,
                'razorpay_payment_id'  => $request->razorpay_payment_id,
                'razorpay_signature'   => $request->razorpay_signature,
                'amount_paid'          => $plan->price,
                'currency'             => 'INR',
            ]
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Payment successful! Welcome to Rakhi Premium.',
            'ends_at'  => now()->addDays($plan->duration_days),
        ]);
    }

    // Get subscription status
    public function status()
    {
        $user = auth()->user();
        $sub  = $user->subscription;

        return response()->json([
            'success'      => true,
            'is_subscribed'=> $user->isSubscribed(),
            'subscription' => $sub,
        ]);
    }

    // Cancel subscription
    public function cancel()
    {
        $sub = auth()->user()->subscription;

        if ($sub) {
            $sub->update(['status' => 'cancelled']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.'
        ]);
    }
}
