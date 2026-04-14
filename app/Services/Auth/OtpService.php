<?php

namespace App\Services\Auth;

use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $otpTtlMinutes  = 10;
    private int $maxSendPerHour = 3;
    private int $maxVerifyTries = 3;

    public function generate(string $mobile): string
    {
        $otp = strval(rand(100000, 999999));

        $this->cachePut("otp_{$mobile}", $otp, $this->otpTtlMinutes * 60);
        $this->cachePut("otp_tries_{$mobile}", 0, $this->otpTtlMinutes * 60);

        return $otp;
    }

    public function canSend(string $mobile): bool
    {
        return $this->cacheGet("otp_send_count_{$mobile}", 0) < $this->maxSendPerHour;
    }

    public function incrementSendCount(string $mobile): void
    {
        $count = $this->cacheGet("otp_send_count_{$mobile}", 0);
        $this->cachePut("otp_send_count_{$mobile}", $count + 1, 3600);
    }

    public function verify(string $mobile, string $otp): array
    {
        $cached = $this->cacheGet("otp_{$mobile}");

        if (!$cached) {
            return ['success' => false, 'message' => 'OTP expired or not found. Please request a new one.'];
        }

        $tries = (int) $this->cacheGet("otp_tries_{$mobile}", 0);

        if ($tries >= $this->maxVerifyTries) {
            $this->cacheForget("otp_{$mobile}");
            $this->cacheForget("otp_tries_{$mobile}");
            return ['success' => false, 'message' => 'Too many attempts. Please request a new OTP.'];
        }

        if ($cached !== $otp) {
            $this->cachePut("otp_tries_{$mobile}", $tries + 1, $this->otpTtlMinutes * 60);
            $remaining = $this->maxVerifyTries - $tries - 1;
            return ['success' => false, 'message' => "Invalid OTP. {$remaining} attempts remaining."];
        }

        $this->cacheForget("otp_{$mobile}");
        $this->cacheForget("otp_tries_{$mobile}");

        return ['success' => true, 'message' => 'OTP verified'];
    }

    public function send(string $mobile, string $otp): bool
    {
        // On local — log OTP and skip real send
        if (app()->environment('local')) {
            Log::info("[OTP LOCAL] mobile={$mobile} otp={$otp}");
            return true;
        }

        $apiKey     = ApiConfigService::get('msg91', 'api_key');
        $templateId = ApiConfigService::get('msg91', 'template_id');

        if (empty($apiKey) || empty($templateId)) {
            Log::error('MSG91 config missing in api_services table. Set api_key and template_id from Admin Panel.');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'authkey'      => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post('https://api.msg91.com/api/v5/otp', [
                    'template_id' => $templateId,
                    'mobile'      => '91' . $mobile,
                    'otp'         => $otp,
                ]);

            if ($response->successful()) {
                Log::info("OTP sent successfully to {$mobile}");
                return true;
            }

            Log::error('MSG91 OTP send failed', [
                'mobile'   => $mobile,
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('OTP send exception: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Cache helpers — never crash if cache fails ───────────────────────────

    private function cachePut(string $key, mixed $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (\Exception $e) {
            Log::warning("OTP cache put failed for {$key}: " . $e->getMessage());
        }
    }

    private function cacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            Log::warning("OTP cache get failed for {$key}: " . $e->getMessage());
            return $default;
        }
    }

    private function cacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Exception $e) {
            // non-fatal
        }
    }
}
