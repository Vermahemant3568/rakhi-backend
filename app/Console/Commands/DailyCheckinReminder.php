<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;

class DailyCheckinReminder extends Command
{
    protected $signature   = 'rakhi:checkin-reminder';
    protected $description = 'Send daily check-in reminder to users';

    public function handle(PushNotificationService $push): void
    {
        $today = now()->toDateString();

        $users = User::where('is_active', 1)
            ->where('is_banned', 0)
            ->where('onboarding_complete', 1)
            ->where('notification_enabled', 1)
            ->whereNotNull('fcm_token')
            ->whereDoesntHave('dailyCheckins', fn($q) => $q->where('checkin_date', $today))
            ->get();

        $this->info("Sending reminders to {$users->count()} users");

        foreach ($users as $user) {
            $push->sendToUser(
                user:  $user,
                title: 'Hey ' . ($user->first_name ?? 'there') . '! 🌸',
                body:  'Rakhi is waiting for your daily check-in. How are you feeling today?',
                data:  ['screen' => 'checkin']
            );
        }

        $this->info('Done!');
    }
}
