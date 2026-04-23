<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ProactiveReminder;
use App\Models\User;
use App\Models\UserMemory;
use App\Services\Notification\PushNotificationService;
use Illuminate\Support\Facades\Log;

class ProactiveReminderService
{
    private const COOLDOWN_HOURS = 20;
    private const FOLLOWUP_SILENCE_HOURS = 3;

    public function __construct(
        private PushNotificationService $push
    ) {}

    // ─────────────────────────────────────────
    // MAIN FLOW
    // ─────────────────────────────────────────

    public function processUser(User $user): bool
    {
        if (!$this->isEligible($user)) return false;

        $memory = UserMemory::where('user_id', $user->id)
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        if (empty($memory)) return false;

        $sent = false;

        if ($this->shouldSendMedicationReminder($user, $memory)) {
            $this->sendReminder($user, 'medication', $this->buildMedicationMessage($user, $memory));
            $sent = true;
        }

        if (!$sent && $this->shouldSendFollowUp($user)) {
            $this->sendReminder($user, 'followup', $this->buildFollowUpMessage($user));
            $sent = true;
        }

        if (!$sent && $this->shouldSendMealReminder($user, $memory)) {
            $this->sendReminder($user, 'meal', $this->buildMealMessage($user, $memory));
            $sent = true;
        }

        return $sent;
    }

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
    // MEDICATION
    // ─────────────────────────────────────────

    private function shouldSendMedicationReminder(User $user, array $memory): bool
    {
        $medications = strtolower($memory['medications'] ?? '');
        $condition   = strtolower($memory['health_condition'] ?? '');

        $critical = str_contains($medications, 'insulin')
            || str_contains($medications, 'metformin')
            || str_contains($condition, 'diabetes');

        if (!$critical) return false;

        $hour = now()->timezone('Asia/Kolkata')->hour;
        if ($hour < 18 || $hour > 21) return false;

        return $this->notSentRecently($user, 'medication');
    }

    private function buildMedicationMessage(User $user, array $memory): string
    {
        $name = $user->first_name ?? '';

        return $name
            ? "Hey {$name} 😊\n\nAaj meds li kya? Dinner ke around miss ho jata hai kabhi kabhi."
            : "Hey 😊\n\nAaj meds li kya? Dinner ke time yaad rehna thoda tricky hota hai.";
    }

    // ─────────────────────────────────────────
    // FOLLOW-UP
    // ─────────────────────────────────────────

    private function shouldSendFollowUp(User $user): bool
    {
        $last = ProactiveReminder::where('user_id', $user->id)
            ->where('user_responded', false)
            ->where('reminder_type', '!=', 'followup')
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$last) return false;

        if ($last->sent_at->diffInHours(now()) < self::FOLLOWUP_SILENCE_HOURS) {
            return false;
        }

        return $this->notSentRecently($user, 'followup', 12);
    }

    private function buildFollowUpMessage(User $user): string
    {
        $name = $user->first_name ?? '';

        return $name
            ? "Hey {$name} 💙\n\nBas check kar rahi thi — sab theek hai na?"
            : "Hey 💙\n\nBas check kar rahi thi — sab theek chal raha hai na?";
    }

    // ─────────────────────────────────────────
    // MEAL
    // ─────────────────────────────────────────

    private function shouldSendMealReminder(User $user, array $memory): bool
    {
        $condition = strtolower($memory['health_condition'] ?? '');

        $important = str_contains($condition, 'diabetes')
            || str_contains($condition, 'pcos')
            || str_contains($condition, 'thyroid');

        if (!$important) return false;

        $hour = now()->timezone('Asia/Kolkata')->hour;
        if ($hour < 12 || $hour > 14) return false;

        return $this->notSentRecently($user, 'meal');
    }

    private function buildMealMessage(User $user, array $memory): string
    {
        $name = $user->first_name ?? '';

        return $name
            ? "Hey {$name} 🍱\n\nLunch ho gaya ya abhi pending hai?"
            : "Hey 🍱\n\nLunch ho gaya ya abhi reh gaya?";
    }

    // ─────────────────────────────────────────
    // HELPERS
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
            $session = ChatSession::where('user_id', $user->id)
                ->where('session_type', 'chat')
                ->where('status', 'active')
                ->orderBy('id', 'desc')
                ->first();

            if ($session) {
                ChatMessage::create([
                    'session_id' => $session->id,
                    'user_id'    => $user->id,
                    'role'       => 'rakhi',
                    'message'    => $message,
                    'message_type' => 'text',
                ]);
            }

            $firstLine = explode("\n", $message)[0];

            $this->push->sendToUser(
                user: $user,
                title: 'Rakhi 🌸',
                body: $firstLine,
                data: ['screen' => 'chat', 'session_id' => (string) ($session?->id ?? '')]
            );

            ProactiveReminder::create([
                'user_id'       => $user->id,
                'reminder_type' => $type,
                'habit_key'     => $type . '_' . now()->format('Y-m-d'),
            ]);

            Log::info("Reminder sent: user={$user->id}, type={$type}");

        } catch (\Exception $e) {
            Log::error("Reminder failed: user={$user->id} " . $e->getMessage());
        }
    }
}
