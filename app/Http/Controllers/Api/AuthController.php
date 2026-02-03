<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10'
        ]);

        $otp = rand(100000, 999999);

        DB::table('otp_logs')->insert([
            'mobile' => $request->mobile,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(1),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // TODO: integrate SMS provider later
        // For now log OTP
        \Log::info("OTP for {$request->mobile} is {$otp}");

        return response()->json([
            'success' => true,
            'message' => 'OTP sent'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
            'otp' => 'required|digits:6'
        ]);

        $otpRow = DB::table('otp_logs')
            ->where('mobile', $request->mobile)
            ->where('otp', $request->otp)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpRow) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 401);
        }

        DB::table('otp_logs')
            ->where('id', $otpRow->id)
            ->update(['is_used' => true]);

        $user = User::with('subscription')->firstOrCreate(
            ['mobile' => $request->mobile],
            ['mobile_verified_at' => now()]
        );

        $token = $user->createToken('user-token')->plainTextToken;

        // Determine subscription status
        $subscriptionStatus = 'none';
        if ($user->subscription) {
            $now = now();
            if ($user->subscription->status === 'active' && $user->subscription->current_period_end > $now) {
                $subscriptionStatus = 'active';
            } elseif ($user->subscription->status === 'trial' && $user->subscription->trial_end > $now) {
                $subscriptionStatus = 'trial';
            } else {
                $subscriptionStatus = 'expired';
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'is_onboarded' => $user->is_onboarded,
                'subscription_status' => $subscriptionStatus
            ]
        ]);
    }
}
