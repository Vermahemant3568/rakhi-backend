<?php

namespace App\Console\Commands;

use App\Models\UserStreak;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;

class StreakChecker extends Command
{
    protected $signature   = 'rakhi:streak-check';
    protected $description = 'Reset streaks for users who missed yesterday';

    public function handle(PushNotificationService $push): void
    {
        $yesterday = now()->subDay()->toDateString();

        $broken = UserStreak::where('current_streak', '>', 0)
            ->where('last_checkin_date', '<', $yesterday)
            ->with('user')
            ->get();

        foreach ($broken as $streak) {
            $oldStreak = $streak->current_streak;
            $streak->update(['current_streak' => 0]);

            if ($streak->user && $oldStreak >= 3) {
                $push->sendToUser(
                    user:  $streak->user,
                    title: 'Your streak ended 😢',
                    body:  "Your {$oldStreak}-day streak ended. But that's okay! Start fresh today. Rakhi believes in you! 💪",
                    data:  ['screen' => 'checkin']
                );
            }
        }

        $this->info("Reset {$broken->count()} broken streaks");
    }
}
