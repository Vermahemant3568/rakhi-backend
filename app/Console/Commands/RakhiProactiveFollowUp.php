<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AI\EngagementTracker;
use App\Services\AI\ProactiveReminderService;
use Illuminate\Console\Command;

class RakhiProactiveFollowUp extends Command
{
    protected $signature   = 'rakhi:proactive-followup';
    protected $description = 'Send proactive reminders, update engagement states, and escalate non-responsive high-risk users';

    public function handle(ProactiveReminderService $reminderService, EngagementTracker $engagement): void
    {
        // Reset daily escalation call counters for a new day
        $engagement->resetDailyCallCounts();

        $users = User::where('is_active', 1)
            ->where('is_banned', 0)
            ->where('onboarding_complete', 1)
            ->where('first_consultation_complete', 1)
            ->where('notification_enabled', 1)
            ->whereNotNull('fcm_token')
            ->get();

        $this->info("Processing {$users->count()} users...");

        $sent      = 0;
        $escalated = 0;

        foreach ($users as $user) {
            // Standard reminders (medication, meal, engagement, follow-up)
            if ($reminderService->processUser($user)) {
                $sent++;
            }

            // Escalation pass — voice call for non-responsive high-risk users
            if ($reminderService->processEscalation($user)) {
                $escalated++;
            }
        }

        $this->info("Done. Reminders sent: {$sent} | Escalation calls triggered: {$escalated}");
    }
}
