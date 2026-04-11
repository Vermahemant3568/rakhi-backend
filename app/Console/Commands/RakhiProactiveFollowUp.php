<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AI\ProactiveReminderService;
use Illuminate\Console\Command;

class RakhiProactiveFollowUp extends Command
{
    protected $signature   = 'rakhi:proactive-followup';
    protected $description = 'Send proactive habit reminders and follow-ups from Rakhi';

    public function handle(ProactiveReminderService $reminderService): void
    {
        $users = User::where('is_active', 1)
            ->where('is_banned', 0)
            ->where('onboarding_complete', 1)
            ->where('first_consultation_complete', 1)
            ->where('notification_enabled', 1)
            ->whereNotNull('fcm_token')
            ->get();

        $this->info("Processing {$users->count()} users for proactive reminders...");

        $sent = 0;
        foreach ($users as $user) {
            if ($reminderService->processUser($user)) {
                $sent++;
            }
        }

        $this->info("Done. Sent {$sent} reminders.");
    }
}
