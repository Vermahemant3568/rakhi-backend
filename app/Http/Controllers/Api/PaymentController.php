<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Carbon\Carbon;

class PaymentController extends Controller
{
    private function razorpay()
    {
        return new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }

    /**
     * Create order for ₹7 trial
     */
    public function createTrialOrder(Request $request)
    {
        $user = $request->user();

        $api = $this->razorpay();

        $order = $api->order->create([
            'amount' => 700, // ₹7 in paise
            'currency' => 'INR',
            'payment_capture' => 1
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order['id'],
                'amount' => 700,
                'currency' => 'INR'
            ]
        ]);
    }

    /**
     * Verify trial payment & start trial
     */
    public function verifyTrialPayment(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'razorpay_payment_id' => 'required|string'
        ]);

        // Payment assumed verified (signature verification added later)

        UserSubscription::create([
            'user_id' => $user->id,
            'status' => 'trial',
            'trial_start' => now(),
            'trial_end' => now()->addDays(7),
            'payment_provider' => 'razorpay'
        ]);

        PaymentTransaction::create([
            'user_id' => $user->id,
            'type' => 'trial',
            'amount' => 7,
            'payment_provider' => 'razorpay',
            'provider_payment_id' => $request->razorpay_payment_id,
            'status' => 'success'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trial activated'
        ]);
    }

    /**
     * Create monthly subscription (₹299 autopay)
     */
    public function createMonthlySubscription(Request $request)
    {
        $api = $this->razorpay();

        $subscription = $api->subscription->create([
            'plan_id' => 'plan_xxxxx', // Razorpay plan
            'total_count' => 12,
            'customer_notify' => 1
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'subscription_id' => $subscription['id']
            ]
        ]);
    }

    /**
     * Verify monthly subscription
     */
    public function verifyMonthlySubscription(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'razorpay_subscription_id' => 'required|string'
        ]);

        UserSubscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'active',
                'provider_subscription_id' => $request->razorpay_subscription_id,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'payment_provider' => 'razorpay'
            ]
        );

        PaymentTransaction::create([
            'user_id' => $user->id,
            'type' => 'subscription',
            'amount' => 299,
            'payment_provider' => 'razorpay',
            'provider_subscription_id' => $request->razorpay_subscription_id,
            'status' => 'success'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription active'
        ]);
    }
}