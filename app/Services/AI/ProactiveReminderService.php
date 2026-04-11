<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ProactiveReminder;
use App\Models\User;
use App\Models\UserMemory;
use App\Services\Notification\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProactiveReminderService
{
    // Minimum hours between same-type reminders (anti-spam)
    private const COOLDOWN_HOURS = 20;

    // Hours of user silence before sending a follow-up
    private const FOLLOWUP_SILENCE_HOURS = 3;

    public function __construct(
        private PushNotificationService $push
    ) {}

    /**
     * Main entry — called by the scheduled command for each user.
     * Returns true if any reminder was sent.
     */
    public function processUser(User $user): bool
    {
        if (!$this->isEligible($user)) return false;

        $memory = UserMemory::where('user_id', $user->id)
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        if (empty($memory)) return false;

        $sent = false;

        // 1. Check critical medication reminders (highest priority)
        if ($this->shouldSendMedicationReminder($user, $memory)) {
            $this->sendReminder($user, 'medication', $this->buildMedicationMessage($user, $memory));
            $sent = true;
        }

        // 2. Check follow-up if user hasn't responded to a previous reminder
        if (!$sent && $this->shouldSendFollowUp($user)) {
            $this->sendReminder($user, 'followup', $this->buildFollowUpMessage($user));
            $sent = true;
        }

        // 3. Meal reminder — only if no medication reminder was sent
        if (!$sent && $this->shouldSendMealReminder($user, $memory)) {
            $this->sendReminder($user, 'meal', $this->buildMealMessage($user, $memory));
            $sent = true;
        }

        return $sent;
    }

    /**
     * Mark that user responded — called from ChatController on every user message.
     */
    public function markUserResponded(User $user): void
    {
        ProactiveReminder::where('user_id', $user->id)
            ->where('user_responded', false)
            ->whereNull('responded_at')
            ->update([
                'user_responded' => true,
                'responded_at'   => now(),
            ]);
    }

    // ─────────────────────────────────────────
    // ELIGIBILITY
    // ─────────────────────────────────────────

    private function isEligible(User $user): bool
    {
        return $user->is_active
            && !$user->is_banned
            && $user->first_consultation_complete
            && $user->notification_enabled
            && $user->fcm_token;
    }

    // ─────────────────────────────────────────
    // MEDICATION REMINDER
    // ─────────────────────────────────────────

    private function shouldSendMedicationReminder(User $user, array $memory): bool
    {
        $medications = strtolower($memory['medications'] ?? '');
        $condition   = strtolower($memory['health_condition'] ?? '');

        // Only trigger for critical medications
        $isCritical = str_contains($medications, 'insulin')
            || str_contains($medications, 'metformin')
            || str_contains($condition, 'diabetes')
            || str_contains($condition, 'type 1')
            || str_contains($condition, 'type 2');

        if (!$isCritical) return false;

        // Only send around dinner time (6 PM – 9 PM IST)
        $hour = now()->timezone('Asia/Kolkata')->hour;
        if ($hour < 18 || $hour > 21) return false;

        return $this->notSentRecently($user, 'medication');
    }

    private function buildMedicationMessage(User $user, array $memory): string
    {
        $name      = $user->first_name ?? '';
        $nameStr   = $name ? "Hey {$name}" : "Hey";
        $condition = strtolower($memory['health_condition'] ?? '');

        if (str_contains($condition, 'diabetes') || str_contains($memory['medications'] ?? '', 'insulin')) {
            return "{$nameStr} — just checking in 😊\n\nDid you take your insulin before dinner today?";
        }

        $med = $memory['medications'] ?? 'your medication';
        return "{$nameStr} — quick check 😊\n\nDid you take {$med} today?";
    }

    // ─────────────────────────────────────────
    // FOLLOW-UP (user didn't respond to last reminder)
    // ─────────────────────────────────────────

    private function shouldSendFollowUp(User $user): bool
    {
        $lastReminder = ProactiveReminder::where('user_id', $user->id)
            ->where('user_responded', false)
            ->where('reminder_type', '!=', 'followup')
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$lastReminder) return false;

        // Only follow up if reminder was sent 3+ hours ago and no response
        $hoursSince = $lastReminder->sent_at->diffInHours(now());
        if ($hoursSince < self::FOLLOWUP_SILENCE_HOURS) return false;

        return $this->notSentRecently($user, 'followup', hours: 12);
    }

    private function buildFollowUpMessage(User $user): string
    {
        $name    = $user->first_name ?? '';
        $nameStr = $name ? "Hey {$name}" : "Hey";

        return "{$nameStr}, I just wanted to make sure you're okay 💙\n\nDo you want me to call you quickly, or just let me know how you're doing?";
    }

    // ─────────────────────────────────────────
    // MEAL REMINDER
    // ─────────────────────────────────────────

    private function shouldSendMealReminder(User $user, array $memory): bool
    {
        $condition = strtolower($memory['health_condition'] ?? '');

        // Only for users with conditions where meal timing matters
        $mealCritical = str_contains($condition, 'diabetes')
            || str_contains($condition, 'pcos')
            || str_contains($condition, 'thyroid');

        if (!$mealCritical) return false;

        // Send around lunch time (12 PM – 2 PM IST)
        $hour = now()->timezone('Asia/Kolkata')->hour;
        if ($hour < 12 || $hour > 14) return false;

        return $this->notSentRecently($user, 'meal');
    }

    private function buildMealMessage(User $user, array $memory): string
    {
        $name    = $user->first_name ?? '';
        $nameStr = $name ? "Hey {$name}" : "Hey";
        $goal    = strtolower($memory['main_goal'] ?? '');

        if (str_contains($goal, 'sugar') || str_contains($goal, 'diabetes')) {
            return "{$nameStr} — lunchtime! 🍱\n\nHave you eaten yet? Keeping meal timing consistent really helps with sugar levels.";
        }

        return "{$nameStr} — just a gentle nudge 😊\n\nHave you had lunch yet? Skipping meals can work against your goals.";
    }

    // ─────────────────────────────────────────
    // SHARED HELPERS
    // ─────────────────────────────────────────

    private function notSentRecently(User $user, string $type, int $hours = self::COOLDOWN_HOURS): bool
    {
        return !ProactiveReminder::where('user_id', $user->id)
            ->where('reminder_type', $type)
            ->where('sent_at', '>=', now()->subHours($hours))
            ->exists();
    }

    private function sendReminder(User $user, string $type, string $message): void
    {
        try {
            // 1. Save as a Rakhi chat message in the user's active session
            $session = ChatSession::where('user_id', $user->id)
                ->where('session_type', 'chat')
                ->where('status', 'active')
                ->orderBy('id', 'desc')
                ->first();

            if ($session) {
                ChatMessage::create([
                    'session_id'   => $session->id,
                    'user_id'      => $user->id,
                    'role'         => 'rakhi',
                    'message'      => $message,
                    'message_type' => 'text',
                ]);
            }

            // 2. Push notification
            $firstLine = explode("\n", $message)[0];
            $this->push->sendToUser(
                user:  $user,
                title: 'Rakhi 🌸',
                body:  $firstLine,
                data:  ['screen' => 'chat', 'session_id' => (string) ($session?->id ?? '')]
            );

            // 3. Record the reminder
            ProactiveReminder::create([
                'user_id'       => $user->id,
                'reminder_type' => $type,
                'habit_key'     => $type . '_' . now()->format('Y-m-d'),
            ]);

            Log::info("Proactive reminder sent: user={$user->id} type={$type}");

        } catch (\Exception $e) {
            Log::error("ProactiveReminder send failed: user={$user->id} " . $e->getMessage());
        }
    }
}
