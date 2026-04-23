<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStreak;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    // Screen 2 — Send OTP
    public function sendOtp(Request $request)
    {
        \Log::info('=== OTP REQUEST RECEIVED ===', [
            'ip' => $request->ip(),
            'body' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        $request->validate([
            'mobile' => 'required|digits:10',
        ]);

        if (!$this->otpService->canSend($request->mobile)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please try again after 1 hour.',
            ], 429);
        }

        $testMode = $this->otpService->isTestMode();

        // LIVE mode — must have an active provider before generating OTP
        if (!$testMode && !$this->otpService->hasActiveProvider()) {
            return response()->json([
                'success' => false,
                'message' => 'SMS service is not configured. Please contact support.',
            ], 503);
        }

        $otp = $this->otpService->generate($request->mobile);

        if (!$testMode) {
            $sent = $this->otpService->send($request->mobile, $otp);
            if (!$sent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP. Please try again.',
                ], 503);
            }
        }

        $this->otpService->incrementSendCount($request->mobile);

        return response()->json([
            'success'   => true,
            'message'   => 'OTP sent successfully',
            'otp_debug' => $testMode ? $otp : null,
        ]);
    }

    // Screen 3 — Verify OTP + Login or Register
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
            'otp'    => 'required|digits:6',
        ]);

        $result = $this->otpService->verify($request->mobile, $request->otp);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        // Find or create user
        $user = User::firstOrCreate(
            ['mobile' => $request->mobile],
            ['is_active' => 1]
        );

        // Create streak record if new user
        if (!$user->streak) {
            UserStreak::create(['user_id' => $user->id]);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success'             => true,
            'token'               => $token,
            'user'                => $user,
            'is_new_user'         => !$user->onboarding_complete,
            'onboarding_step'     => $user->onboarding_step,
        ]);
    }

    // Get current user
    public function me()
    {
        $user = auth()->user()->load([
            'language',
            'goals',
            'coaches',
            'subscription',
            'streak',
        ]);

        return response()->json([
            'success' => true,
            'user'    => $user,
        ]);
    }

    // Update FCM token for push notifications
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        auth()->user()->update([
            'fcm_token' => $request->fcm_token
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated'
        ]);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token already expired or invalid — treat as logged out
        }
        return response()->json([
            'success' => true,
            'message' => 'Logged out'
        ]);
    }
}
