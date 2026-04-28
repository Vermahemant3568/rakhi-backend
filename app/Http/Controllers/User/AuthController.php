<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStreak;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    // ─── Send OTP ─────────────────────────────────────────────────────────────

    public function sendOtp(Request $request)
    {
        \Log::info('=== OTP REQUEST RECEIVED ===', [
            'ip'      => $request->ip(),
            'body'    => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        $request->validate(['mobile' => 'required|digits:10']);

        if (!$this->otpService->canSend($request->mobile)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please try again after 1 hour.',
            ], 429);
        }

        $testMode = $this->otpService->isTestMode();

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

    // ─── Verify OTP + Login / Register ────────────────────────────────────────

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

        $user = User::firstOrCreate(
            ['mobile' => $request->mobile],
            ['is_active' => 1]
        );

        if (!$user->streak) {
            UserStreak::create(['user_id' => $user->id]);
        }

        $token = JWTAuth::fromUser($user);
        $ttl   = (int) config('jwt.ttl');

        return response()->json([
            'success'            => true,
            'token'              => $token,
            'token_type'         => 'bearer',
            'expires_in'         => $ttl * 60,
            'expires_at'         => now()->addMinutes($ttl)->toIso8601String(),
            'refresh_ttl'        => (int) config('jwt.refresh_ttl') * 60,
            'user'               => $user,
            'is_new_user'        => !$user->onboarding_complete,
            'onboarding_step'    => $user->onboarding_step,
            'consultation_state' => $user->consultation_state,
        ]);
    }

    // ─── Silent Token Refresh ─────────────────────────────────────────────────
    // Called by the client before the token expires (or on 401 with code=session_expired).
    // Returns a fresh token without requiring re-authentication.

    public function refresh(Request $request)
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            $ttl = (int) config('jwt.ttl');
            return response()->json([
                'success'    => true,
                'token'      => $newToken,
                'token_type' => 'bearer',
                'expires_in' => $ttl * 60,
                'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
            ]);

        } catch (TokenExpiredException $e) {
            // Refresh window expired — must re-authenticate
            return response()->json([
                'success' => false,
                'code'    => 'refresh_expired',
                'message' => 'Session fully expired. Please login again.',
            ], 401);
        } catch (TokenInvalidException | JWTException $e) {
            return response()->json([
                'success' => false,
                'code'    => 'token_invalid',
                'message' => 'Invalid token. Please login again.',
            ], 401);
        }
    }

    // ─── Fast Session Validate ────────────────────────────────────────────────
    // Lightweight endpoint: client calls this on app open to confirm session is
    // still valid. Returns user data + fresh token metadata. No heavy DB joins.

    public function validate(Request $request)
    {
        // UserAuth middleware already authenticated the user by this point.
        // If we reach here, the session is valid (or was silently refreshed).
        $user = auth()->user();

        return response()->json([
            'success'            => true,
            'valid'              => true,
            'user'               => $user,
            'consultation_state' => $user->consultation_state,
            'onboarding_complete'=> $user->onboarding_complete,
            'expires_at'         => now()->addMinutes((int) config('jwt.ttl'))->toIso8601String(), // (int) cast already applied
        ]);
    }

    // ─── Get Current User ─────────────────────────────────────────────────────

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

    // ─── Update FCM Token ─────────────────────────────────────────────────────

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        auth()->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['success' => true, 'message' => 'FCM token updated']);
    }

    // ─── Update Profile ───────────────────────────────────────────────────────

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'nullable|email|max:255',
        ]);

        auth()->user()->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['success' => true, 'user' => auth()->user()]);
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token already expired or invalid — treat as logged out
        }

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }
}
