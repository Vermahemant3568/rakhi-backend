<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $otpTtlMinutes   = 10;
    private int $maxSendPerHour  = 3;
    private int $maxVerifyTries  = 3;

    public function generate(string $mobile): string
    {
        $otp = strval(rand(100000, 999999));

        Cache::put("otp_{$mobile}", $otp, now()->addMinutes($this->otpTtlMinutes));
        Cache::put("otp_tries_{$mobile}", 0, now()->addMinutes($this->otpTtlMinutes));

        return $otp;
    }

    public function canSend(string $mobile): bool
    {
        $count = Cache::get("otp_send_count_{$mobile}", 0);
        return $count < $this->maxSendPerHour;
    }

    public function incrementSendCount(string $mobile): void
    {
        $key = "otp_send_count_{$mobile}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    public function verify(string $mobile, string $otp): array
    {
        $cached = Cache::get("otp_{$mobile}");

        if (!$cached) {
            return ['success' => false, 'message' => 'OTP expired or not found'];
        }

        $tries = (int) Cache::get("otp_tries_{$mobile}", 0);

        if ($tries >= $this->maxVerifyTries) {
            Cache::forget("otp_{$mobile}");
            Cache::forget("otp_tries_{$mobile}");
            return ['success' => false, 'message' => 'Too many attempts. Please request a new OTP'];
        }

        if ($cached !== $otp) {
            Cache::put("otp_tries_{$mobile}", $tries + 1, now()->addMinutes($this->otpTtlMinutes));
            $remaining = $this->maxVerifyTries - $tries - 1;
            return ['success' => false, 'message' => "Invalid OTP. {$remaining} attempts remaining"];
        }

        Cache::forget("otp_{$mobile}");
        Cache::forget("otp_tries_{$mobile}");

        return ['success' => true, 'message' => 'OTP verified'];
    }

    public function send(string $mobile, string $otp): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        try {
            $response = Http::withHeaders([
                'authkey'      => config('services.msg91.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.msg91.com/api/v5/otp', [
                'template_id' => config('services.msg91.template_id'),
                'mobile'      => '91' . $mobile,
                'otp'         => $otp,
            ]);

            if (!$response->successful()) {
                Log::error('MSG91 OTP send failed', [
                    'mobile'   => $mobile,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('OTP send exception: ' . $e->getMessage());
            return false;
        }
    }
}
