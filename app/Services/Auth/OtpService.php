<?php

namespace App\Services\Auth;

use App\Models\ApiService;
use App\Models\OtpLog;
use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $maxSendPerHour = 3;
    private int $otpTtlSeconds  = 300; // 5 minutes
    private int $maxAttempts    = 3;

    // ─── Mode ─────────────────────────────────────────────────────────────────

    public function isTestMode(): bool
    {
        $row  = ApiService::where('service_name', 'otp_mode')->first();
        $mode = strtoupper($row?->config['mode'] ?? 'TEST');
        return $mode !== 'LIVE';
    }

    public function hasActiveProvider(): bool
    {
        try {
            $msg91    = ApiService::where('service_name', 'msg91')->first();
            $fast2sms = ApiService::where('service_name', 'fast2sms')->first();

            $msg91Active    = $msg91?->is_active
                && !empty($msg91->config['api_key'])
                && !empty($msg91->config['template_id']);

            $fast2smsActive = $fast2sms?->is_active
                && !empty($fast2sms->config['api_key']);

            return $msg91Active || $fast2smsActive;
        } catch (\Exception $e) {
            Log::error('OTP: provider check failed — ' . $e->getMessage());
            return false;
        }
    }

    // ─── Rate limiting (DB-based, no cache) ──────────────────────────────────

    public function canSend(string $mobile): bool
    {
        try {
            $count = OtpLog::where('phone', $mobile)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            return $count < $this->maxSendPerHour;
        } catch (\Exception $e) {
            Log::error('OTP canSend check failed: ' . $e->getMessage());
            return true; // fail open — don't block user if DB check fails
        }
    }

    public function incrementSendCount(string $mobile): void
    {
        // No-op — count is derived from otp_logs table directly in canSend()
    }

    // ─── Generate & ALWAYS store in DB ───────────────────────────────────────

    public function generate(string $mobile): string
    {
        $otp = strval(rand(100000, 999999));

        try {
            // Invalidate all previous unused OTPs for this phone
            OtpLog::where('phone', $mobile)
                ->where('is_used', 0)
                ->update(['is_used' => 1]);

            OtpLog::create([
                'phone'      => $mobile,
                'otp'        => $otp,
                'is_used'    => 0,
                'expires_at' => now()->addSeconds($this->otpTtlSeconds),
            ]);

            Log::info("OTP generated and stored in DB for {$mobile}");
        } catch (\Exception $e) {
            Log::error("OTP DB write failed for {$mobile}: " . $e->getMessage());
        }

        return $otp;
    }

    // ─── Send SMS (LIVE mode only, active provider only) ─────────────────────

    public function send(string $mobile, string $otp): bool
    {
        if ($this->isTestMode()) {
            Log::info("OTP [TEST MODE] — SMS skipped for {$mobile}. OTP: {$otp}");
            return true;
        }

        $msg91 = $this->getActiveMsg91();
        if ($msg91) {
            $sent = $this->sendViaMsg91(
                $mobile,
                $otp,
                $msg91->config['api_key'],
                $msg91->config['template_id']
            );
            if ($sent) return true;
        }

        $fast2sms = $this->getActiveFast2Sms();
        if ($fast2sms) {
            return $this->sendViaFast2Sms($mobile, $otp, $fast2sms->config['api_key']);
        }

        Log::error("OTP [LIVE MODE] — no active SMS provider for {$mobile}.");
        return false;
    }

    private function getActiveMsg91(): ?ApiService
    {
        try {
            $service = ApiService::where('service_name', 'msg91')->first();
            if (
                $service?->is_active
                && !empty($service->config['api_key'])
                && !empty($service->config['template_id'])
            ) {
                return $service;
            }
        } catch (\Exception $e) {
            Log::error('OTP: MSG91 fetch failed — ' . $e->getMessage());
        }
        return null;
    }

    private function getActiveFast2Sms(): ?ApiService
    {
        try {
            $service = ApiService::where('service_name', 'fast2sms')->first();
            if ($service?->is_active && !empty($service->config['api_key'])) {
                return $service;
            }
        } catch (\Exception $e) {
            Log::error('OTP: Fast2SMS fetch failed — ' . $e->getMessage());
        }
        return null;
    }

    private function sendViaMsg91(string $mobile, string $otp, string $apiKey, string $templateId): bool
    {
        try {
            $response = Http::withHeaders([
                'authkey'      => $apiKey,
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/otp', [
                'template_id' => $templateId,
                'mobile'      => '91' . $mobile,
                'otp'         => $otp,
            ]);

            $data = $response->json();
            Log::info('MSG91 response', $data ?? []);
            return isset($data['type']) && $data['type'] === 'success';

        } catch (\Exception $e) {
            Log::error('MSG91 OTP Exception: ' . $e->getMessage());
            return false;
        }
    }

    private function sendViaFast2Sms(string $mobile, string $otp, string $apiKey): bool
    {
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
            return isset($data['return']) && $data['return'] === true;

        } catch (\Exception $e) {
            Log::error('Fast2SMS OTP Exception: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Verify (fully DB-based, zero cache) ─────────────────────────────────

    public function verify(string $mobile, string $otp): array
    {
        $otp = trim($otp);

        Log::info('OTP verify attempt', ['mobile' => $mobile, 'entered' => $otp]);

        try {
            // Count failed attempts in last 5 minutes from DB
            $tries = OtpLog::where('phone', $mobile)
                ->where('is_used', 0)
                ->where('created_at', '>=', now()->subSeconds($this->otpTtlSeconds))
                ->count();

            // Check if locked out — more than maxAttempts OTPs requested recently
            // (each wrong attempt doesn't create a new row, so we track via a separate approach)
            // Use the latest OTP record's attempt_count if needed — for now use simple DB lookup

            $record = OtpLog::where('phone', $mobile)
                ->where('otp', $otp)
                ->where('is_used', 0)
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            if (!$record) {
                return ['success' => false, 'message' => 'Invalid or expired OTP. Please try again.'];
            }

            $record->update(['is_used' => 1]);

            return ['success' => true, 'message' => 'OTP verified'];

        } catch (\Exception $e) {
            Log::error('OTP verify DB error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed. Please try again.'];
        }
    }
}
