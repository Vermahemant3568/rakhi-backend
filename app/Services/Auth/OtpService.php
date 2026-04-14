<?php

namespace App\Services\Auth;

use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $maxSendPerHour = 3;

    // ─── Send OTP (MSG91 generates it automatically) ──────────────────────────

    public function generate(string $mobile): string
    {
        $otp = strval(rand(100000, 999999));
        // Store in cache for local verification only
        $this->cachePut("otp_{$mobile}", $otp, 600);
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

    public function send(string $mobile, string $otp): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $apiKey = ApiConfigService::get('fast2sms', 'api_key');

        if (empty($apiKey)) {
            Log::error('Fast2SMS api_key missing.');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'accept'        => 'application/json',
            ])->post('https://www.fast2sms.com/dev/bulkV2', [
                'route'    => 'q',
                'message'  => "Your Rakhi OTP is {$otp}",
                'language' => 'english',
                'numbers'  => $mobile,
            ]);

            $data = $response->json();

            Log::info('Fast2SMS response', $data ?? []);

            if (isset($data['return']) && $data['return'] === true) {
                return true;
            }

            Log::error('Fast2SMS OTP failed', $data ?? []);
            return false;

        } catch (\Exception $e) {
            Log::error('OTP Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function verify(string $mobile, string $otp): array
    {
        // Fast2SMS has no verify endpoint — verify against cached OTP
        $cached = $this->cacheGet("otp_{$mobile}");

        if (!$cached) {
            return ['success' => false, 'message' => 'OTP expired or not found. Please request a new one.'];
        }

        $tries = (int) $this->cacheGet("otp_tries_{$mobile}", 0);

        if ($tries >= 3) {
            $this->cacheForget("otp_{$mobile}");
            $this->cacheForget("otp_tries_{$mobile}");
            return ['success' => false, 'message' => 'Too many attempts. Please request a new OTP.'];
        }

        if ($cached !== $otp) {
            $this->cachePut("otp_tries_{$mobile}", $tries + 1, 600);
            $remaining = 3 - $tries - 1;
            return ['success' => false, 'message' => "Invalid OTP. {$remaining} attempts remaining."];
        }

        $this->cacheForget("otp_{$mobile}");
        $this->cacheForget("otp_tries_{$mobile}");
        return ['success' => true, 'message' => 'OTP verified'];
    }

    // ─── Cache helpers ────────────────────────────────────────────────────────

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
