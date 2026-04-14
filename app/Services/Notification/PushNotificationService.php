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
        return ApiConfigService::get('firebase', 'server_key', config('services.firebase.server_key'));
    }

    private function projectId(): string
    {
        return ApiConfigService::get('firebase', 'project_id', config('services.firebase.project_id'));
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
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serverKey(),
                'Content-Type'  => 'application/json',
            ])->post(
                "https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send",
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

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Push notification failed: ' . $e->getMessage());
            return false;
        }
    }
}
