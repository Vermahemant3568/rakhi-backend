<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private function serverKey(): string
    {
        return ApiConfigService::get('firebase', 'server_key', config('services.firebase.server_key', ''));
    }

    private function projectId(): string
    {
        return ApiConfigService::get('firebase', 'project_id', config('services.firebase.project_id', ''));
    }

    private function isConfigured(): bool
    {
        return !empty($this->serverKey());
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (!$user->fcm_token || !$user->notification_enabled) {
            return false;
        }

        return $this->send($user->fcm_token, $title, $body, $data);
    }

    public function sendToUsers(array $users, string $title, string $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    private function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Push notification skipped — Firebase server_key not configured in Admin → API Manager.');
            return false;
        }

        try {
            $projectId = $this->projectId();

            // Use FCM v1 API if project_id is available, else fall back to legacy
            if ($projectId) {
                return $this->sendViaFcmV1($token, $title, $body, $data, $projectId);
            }

            return $this->sendViaLegacyFcm($token, $title, $body, $data);

        } catch (\Exception $e) {
            Log::error('Push notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * FCM v1 API (requires project_id + OAuth2 Bearer token = server_key)
     */
    private function sendViaFcmV1(string $token, string $title, string $body, array $data, string $projectId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serverKey(),
            'Content-Type'  => 'application/json',
        ])->post(
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            [
                'message' => [
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => array_map('strval', $data),
                    'android'      => ['priority' => 'high'],
                    'apns'         => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]
        );

        if (!$response->successful()) {
            Log::error('FCM v1 failed: ' . $response->body());
        }

        return $response->successful();
    }

    /**
     * Legacy FCM API (uses server_key directly, no project_id needed)
     */
    private function sendViaLegacyFcm(string $token, string $title, string $body, array $data): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'key=' . $this->serverKey(),
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to'           => $token,
            'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default'],
            'data'         => $data,
            'priority'     => 'high',
        ]);

        if (!$response->successful()) {
            Log::error('FCM legacy failed: ' . $response->body());
        }

        return $response->successful();
    }
}
