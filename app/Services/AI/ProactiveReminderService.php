<?php

namespace App\Services\AI;

use App\Events\VoiceSessionStarted;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ProactiveReminder;
use App\Models\User;
use App\Models\UserMemory;
use App\Services\Notification\PushNotificationService;
use Illuminate\Support\Facades\Log;

class ProactiveReminderService
{
    // High-risk conditions get shorter cooldowns
    private const COOLDOWN_HIGH_RISK = 12;   // hours
    private const COOLDOWN_DEFAULT   = 20;   // hours
    private const FOLLOWUP_SILENCE   = 3;    // hours before sending a follow-up after an unanswered reminder

    // Max reminders per day per user (safety cap)
    private const MAX_DAILY_REMINDERS = 3;

    public function __construct(
        private PushNotificationService $push,
        private EngagementTracker $engagement,
    ) {}

    // ─────────────────────────────────────────
    // MAIN FLOW
    // ─────────────────────────────────────────

    public function processUser(User $user): bool
    {
        if (!$this->isEligible($user)) return false;

        // Refresh engagement state based on silence duration
        $this->engagement->updateState($user);
        $user->refresh();

        // Safety cap — don't spam
        if ($this->dailyReminderCount($user) >= self::MAX_DAILY_REMINDERS) return false;

        $memory    = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $isHighRisk = $this->engagement->isHighRisk($user);
        $cooldown  = $isHighRisk ? self::COOLDOWN_HIGH_RISK : self::COOLDOWN_DEFAULT;

        $sent = false;

        // Daily coaching check-in — for users who have completed consultation
        if (!$sent && $this->shouldSendDailyCheckin($user)) {
            $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
            $lang    = $this->resolveUserLang($user);
            $this->sendReminder($user, 'daily_checkin', $this->buildDailyCheckinMessage($user, $memory, $lang));
            $sent = true;
        }

        if (!$sent && $this->shouldSendMedicationReminder($user, $memory, $cooldown)) {
            $this->sendReminder($user, 'medication', $this->buildMedicationMessage($user, $memory));
            $sent = true;
        }

        if (!$sent && $this->shouldSendMealReminder($user, $memory, $cooldown)) {
            $this->sendReminder($user, 'meal', $this->buildMealMessage($user, $memory));
            $sent = true;
        }

        if (!$sent && $this->shouldSendEngagementReminder($user, $cooldown)) {
            $lang = $this->resolveUserLang($user);
            $this->sendReminder($user, 'engagement', $this->engagement->buildEngagementMessage($user, $lang));
            $sent = true;
        }

        if (!$sent && $this->shouldSendFollowUp($user)) {
            $this->sendReminder($user, 'followup', $this->buildFollowUpMessage($user));
            $sent = true;
        }

        return $sent;
    }

    /**
     * Trigger escalation voice call for non-responsive high-risk users.
     * Called separately from processUser to keep concerns separated.
     */
    public function processEscalation(User $user): bool
    {
        if (!$this->isEligible($user)) return false;

        $this->engagement->updateState($user);
        $user->refresh();

        if (!$this->engagement->shouldEscalateToCall($user)) return false;

        $lang = $this->resolveUserLang($user);

        try {
            $session = $this->getActiveSession($user);
            if (!$session) return false;

            // Create voice session for the escalation call
            $voiceSession = ChatSession::create([
                'user_id'                => $user->id,
                'coach_id'               => $session->coach_id,
                'session_type'           => 'voice',
                'is_first_consultation'  => false,
                'status'                 => 'active',
                'parent_chat_session_id' => $session->id,
            ]);

            $callMsg = $this->engagement->buildEscalationCallMessage($user, $lang);

            // Save call message to chat so user sees context
            ChatMessage::create([
                'session_id'   => $session->id,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $callMsg,
                'message_type' => 'text',
            ]);

            // Broadcast voice session event (frontend handles incoming call UI)
            broadcast(new VoiceSessionStarted($voiceSession));

            // Push notification — feels like a real incoming call
            $this->push->sendToUser($user, 'Rakhi is calling... 📞', $callMsg, [
                'type'                   => 'incoming_call',
                'voice_session_id'       => (string) $voiceSession->id,
                'parent_chat_session_id' => (string) $session->id,
            ]);

            $this->engagement->recordEscalationCall($user);

            Log::info("Escalation call triggered: user={$user->id}");
            return true;

        } catch (\Exception $e) {
            Log::error("Escalation call failed: user={$user->id} " . $e->getMessage());
            return false;
        }
    }

    public function markUserResponded(User $user): void
    {
        $this->engagement->markActive($user);

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

    private function shouldSendMedicationReminder(User $user, array $memory, int $cooldown): bool
    {
        $medications = strtolower($memory['medications'] ?? '');
        $condition   = strtolower($memory['health_condition'] ?? '');

        $critical = str_contains($medications, 'insulin')
            || str_contains($medications, 'metformin')
            || str_contains($condition, 'diabetes');

        if (!$critical) return false;

        $hour = now()->timezone('Asia/Kolkata')->hour;
        if ($hour < 18 || $hour > 21) return false;

        return $this->notSentRecently($user, 'medication', $cooldown);
    }

    private function buildMedicationMessage(User $user, array $memory): string
    {
        $name = $user->first_name ?? '';
        return $name
            ? "Hey {$name} 😊\n\nAaj insulin li kya? Dinner ke around miss ho jata hai kabhi kabhi."
            : "Hey 😊\n\nAaj meds li kya? Dinner ke time yaad rehna thoda tricky hota hai.";
    }

    // ─────────────────────────────────────────
    // MEAL
    // ─────────────────────────────────────────

    private function shouldSendMealReminder(User $user, array $memory, int $cooldown): bool
    {
        $condition = strtolower($memory['health_condition'] ?? '');
        $important = str_contains($condition, 'diabetes')
            || str_contains($condition, 'pcos')
            || str_contains($condition, 'thyroid');

        if (!$important) return false;

        $hour = now()->timezone('Asia/Kolkata')->hour;
        if ($hour < 12 || $hour > 14) return false;

        return $this->notSentRecently($user, 'meal', $cooldown);
    }

    private function buildMealMessage(User $user, array $memory): string
    {
        $name      = $user->first_name ?? '';
        $nameStr   = $name ? "Hey {$name}" : 'Hey';
        $condition = strtolower($memory['health_condition'] ?? '');

        // Vary the message based on condition and time of day
        $hour = now()->timezone('Asia/Kolkata')->hour;

        if ($hour >= 12 && $hour <= 14) {
            if (str_contains($condition, 'diabet')) {
                return "{$nameStr} 🍱\n\nLunch ho gaya? Aaj diet plan mein jo tha — roti-sabzi ya daliya — woh follow ho paya?";
            }
            return "{$nameStr} 🍱\n\nLunch ho gaya ya abhi pending hai?";
        }

        if ($hour >= 18 && $hour <= 20) {
            return "{$nameStr} 🍽️\n\nDinner ka time ho raha hai — aaj ka plan follow ho raha hai?";
        }

        return "{$nameStr} 🍱\n\nAaj ka khana kaisa chal raha hai? Plan ke hisaab se chal rahi hai cheezein?";
    }

    // ─────────────────────────────────────────
    // ENGAGEMENT REMINDER (slow/non-responsive)
    // ─────────────────────────────────────────

    private function shouldSendEngagementReminder(User $user, int $cooldown): bool
    {
        $state = $user->engagement_state ?? 'active';
        if ($state === 'active') return false;

        return $this->notSentRecently($user, 'engagement', $cooldown);
    }

    // ─────────────────────────────────────────────
    // DAILY COACHING CHECK-IN (active_coaching users)
    // Varied, plan-aware, non-repetitive
    // ─────────────────────────────────────────────

    public function shouldSendDailyCheckin(User $user): bool
    {
        if ($user->consultation_state !== 'active_coaching') return false;
        if (!$user->is_active || $user->is_banned)           return false;
        if (!$user->notification_enabled || !$user->fcm_token) return false;

        // Only once per day
        return $this->notSentRecently($user, 'daily_checkin', 20);
    }

    public function sendDailyCheckin(User $user): bool
    {
        if (!$this->shouldSendDailyCheckin($user)) return false;
        if ($this->dailyReminderCount($user) >= self::MAX_DAILY_REMINDERS) return false;

        $memory  = UserMemory::where('user_id', $user->id)->pluck('value', 'key')->toArray();
        $lang    = $this->resolveUserLang($user);
        $message = $this->buildDailyCheckinMessage($user, $memory, $lang);

        $this->sendReminder($user, 'daily_checkin', $message);
        return true;
    }

    public function buildDailyCheckinMessage(User $user, array $memory, string $lang): string
    {
        $name      = $user->first_name ?? '';
        $nameStr   = $name ? "Hey {$name}" : 'Hey';
        $isHindi   = str_starts_with($lang, 'hi') || $lang === 'hi-roman';
        $condition = strtolower($memory['health_condition'] ?? '');
        $hour      = now()->timezone('Asia/Kolkata')->hour;

        // Pick a varied check-in based on time of day and condition
        // Use day-of-week to rotate topics so it never feels repetitive
        $dayOfWeek = now()->dayOfWeek; // 0=Sun, 1=Mon ... 6=Sat

        if (!$isHindi) {
            $messages = [
                "{$nameStr} 🌱 How's the day going? Did you manage to follow the diet plan today?",
                "{$nameStr} 🚶 Quick check-in — did you get any movement in today?",
                "{$nameStr} 💧 How's your energy feeling today? Sleeping okay?",
                "{$nameStr} 🍱 How did meals go today? Anything feel off?",
                "{$nameStr} 🌙 How are you winding down tonight? Following the plan?",
                "{$nameStr} 💪 How's the week going so far? Any wins to share?",
                "{$nameStr} 🌸 Just checking in — how are you feeling overall today?",
            ];
            return $messages[$dayOfWeek % count($messages)];
        }

        // Hindi/Hinglish — condition-aware rotation
        if (str_contains($condition, 'diabet')) {
            $messages = [
                "{$nameStr} 🌱\n\nAaj sugar kaisi rahi? Khana plan ke hisaab se ho paya?",
                "{$nameStr} 🚶\n\nAaj walk ho paya? Khane ke baad 10-15 min bhi kaafi hota hai.",
                "{$nameStr} 🍱\n\nAaj breakfast mein kya liya? Diet plan follow ho raha hai?",
                "{$nameStr} 💧\n\nPaani kitna piya aaj? Aur energy kaisi feel ho rahi hai?",
                "{$nameStr} 🌙\n\nRaat ka khana ho gaya? Dinner plan ke hisaab se tha?",
                "{$nameStr} 💪\n\nIs hafte kaisa chal raha hai overall? Koi cheez jo mushkil lag rahi ho?",
                "{$nameStr} 🌸\n\nBas check kar rahi thi — aaj kaisa feel ho raha hai?",
            ];
        } elseif (str_contains($condition, 'weight')) {
            $messages = [
                "{$nameStr} 🌱\n\nAaj ka din kaisa raha? Diet plan follow ho paya?",
                "{$nameStr} 🚶\n\nAaj exercise ho paya? Fitness plan mein jo tha — woh try kiya?",
                "{$nameStr} 🍱\n\nAaj meals kaisi rahi? Kuch alag khaya ya plan ke hisaab se?",
                "{$nameStr} 💧\n\nEnergy kaisi hai aaj? Neend theek ho rahi hai?",
                "{$nameStr} 🌙\n\nDinner ho gaya? Raat ko late toh nahi khaya?",
                "{$nameStr} 💪\n\nIs hafte koi progress feel ho rahi hai? Chhoti cheezein bhi count karti hain!",
                "{$nameStr} 🌸\n\nBas ek chhota check-in — aaj kaisa feel ho raha hai?",
            ];
        } elseif (str_contains($condition, 'pcos') || str_contains($condition, 'thyroid')) {
            $messages = [
                "{$nameStr} 🌱\n\nAaj kaisa feel ho raha hai? Hormones ke saath din kaisa gaya?",
                "{$nameStr} 🚶\n\nAaj koi movement ho paya? Yoga ya walk bhi kaafi hai.",
                "{$nameStr} 🍱\n\nAaj ka khana kaisa raha? Diet plan follow ho paya?",
                "{$nameStr} 💧\n\nNeend kaisi rahi? Stress toh zyada nahi hai aajkal?",
                "{$nameStr} 🌙\n\nShaam kaisi gayi? Kuch aisa jo share karna ho?",
                "{$nameStr} 💪\n\nIs hafte koi cheez jo acha feel hua? Chhoti wins bhi important hain!",
                "{$nameStr} 🌸\n\nBas check kar rahi thi — sab theek chal raha hai na?",
            ];
        } else {
            // Generic coaching check-ins
            $messages = [
                "{$nameStr} 🌱\n\nAaj ka din kaisa raha? Plan ke hisaab se chal raha hai?",
                "{$nameStr} 🚶\n\nAaj walk ya exercise ho paya?",
                "{$nameStr} 🍱\n\nAaj breakfast kya liya? Healthy start hua?",
                "{$nameStr} 💧\n\nEnergy aur neend kaisi hai aajkal?",
                "{$nameStr} 🌙\n\nDin kaise gaya overall? Kuch share karna ho toh batao.",
                "{$nameStr} 💪\n\nIs hafte koi progress? Chhoti cheezein bhi count karti hain!",
                "{$nameStr} 🌸\n\nBas ek chhota check-in — aaj kaisa feel ho raha hai?",
            ];
        }

        return $messages[$dayOfWeek % count($messages)];
    }

    // ─────────────────────────────────────────
    // FOLLOW-UP (after unanswered reminder)
    // ─────────────────────────────────────────

    private function shouldSendFollowUp(User $user): bool
    {
        $last = ProactiveReminder::where('user_id', $user->id)
            ->where('user_responded', false)
            ->where('reminder_type', '!=', 'followup')
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$last) return false;
        if ($last->sent_at->diffInHours(now()) < self::FOLLOWUP_SILENCE) return false;

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
    // HELPERS
    // ─────────────────────────────────────────

    private function notSentRecently(User $user, string $type, int $hours): bool
    {
        return !ProactiveReminder::where('user_id', $user->id)
            ->where('reminder_type', $type)
            ->where('sent_at', '>=', now()->subHours($hours))
            ->exists();
    }

    private function dailyReminderCount(User $user): int
    {
        return ProactiveReminder::where('user_id', $user->id)
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();
    }

    private function getActiveSession(User $user): ?ChatSession
    {
        return ChatSession::where('user_id', $user->id)
            ->where('session_type', 'chat')
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->first();
    }

    private function resolveUserLang(User $user): string
    {
        $user->loadMissing('language');
        $langName = strtolower($user->language?->name ?? '');
        return match(true) {
            str_contains($langName, 'hindi') => 'hi',
            default                          => 'en',
        };
    }

    private function sendReminder(User $user, string $type, string $message): void
    {
        try {
            $session = $this->getActiveSession($user);

            if ($session) {
                ChatMessage::create([
                    'session_id'   => $session->id,
                    'user_id'      => $user->id,
                    'role'         => 'rakhi',
                    'message'      => $message,
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
