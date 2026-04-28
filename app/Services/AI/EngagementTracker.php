<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\UserMemory;

/**
 * Tracks user engagement state and determines escalation needs.
 *
 * States:
 *   active          — responded within SLOW_HOURS
 *   slow_response   — no response for SLOW_HOURS
 *   non_responsive  — no response for NON_RESPONSIVE_HOURS
 */
class EngagementTracker
{
    // Hours of silence before state transitions
    private const SLOW_HOURS           = 24;
    private const NON_RESPONSIVE_HOURS = 48;

    // Max escalation calls per user per day
    private const MAX_CALLS_PER_DAY = 1;

    // High-risk condition keywords that warrant faster escalation
    private const HIGH_RISK_CONDITIONS = ['diabetes', 'diabet', 'pregnan', 'pcos', 'thyroid', 'hypertension', 'bp'];

    public function updateState(User $user): string
    {
        if (!$user->last_message_at) {
            return $user->engagement_state ?? 'active';
        }

        $hoursSilent = $user->last_message_at->diffInHours(now());

        $state = match(true) {
            $hoursSilent >= self::NON_RESPONSIVE_HOURS => 'non_responsive',
            $hoursSilent >= self::SLOW_HOURS           => 'slow_response',
            default                                    => 'active',
        };

        if ($state !== $user->engagement_state) {
            $user->update(['engagement_state' => $state]);
        }

        return $state;
    }

    public function markActive(User $user): void
    {
        $user->update([
            'engagement_state' => 'active',
            'last_message_at'  => now(),
        ]);
    }

    public function isHighRisk(User $user): bool
    {
        $condition = strtolower(
            UserMemory::where('user_id', $user->id)
                ->where('key', 'health_condition')
                ->value('value') ?? ''
        );

        if (!$condition) {
            $user->loadMissing('goals');
            $condition = strtolower($user->goals->pluck('name')->implode(' '));
        }

        foreach (self::HIGH_RISK_CONDITIONS as $keyword) {
            if (str_contains($condition, $keyword)) return true;
        }

        return false;
    }

    /**
     * Determine if an escalation voice call should be triggered.
     * Returns true only when all conditions are met.
     */
    public function shouldEscalateToCall(User $user): bool
    {
        // Only escalate non-responsive, high-risk users with FCM token
        if ($user->engagement_state !== 'non_responsive') return false;
        if (!$this->isHighRisk($user))                    return false;
        if (!$user->fcm_token)                            return false;
        if (!$user->notification_enabled)                 return false;

        // Respect daily call limit
        if ($user->escalation_call_count >= self::MAX_CALLS_PER_DAY) return false;

        // Don't call again within 24 hours
        if ($user->last_escalation_at && $user->last_escalation_at->diffInHours(now()) < 24) {
            return false;
        }

        return true;
    }

    public function recordEscalationCall(User $user): void
    {
        $user->update([
            'escalation_call_count' => $user->escalation_call_count + 1,
            'last_escalation_at'    => now(),
        ]);
    }

    /**
     * Reset daily escalation counter — called by the nightly cron.
     */
    public function resetDailyCallCounts(): void
    {
        User::where('escalation_call_count', '>', 0)
            ->whereDate('last_escalation_at', '<', today())
            ->update(['escalation_call_count' => 0]);
    }

    /**
     * Build a gentle, human-sounding reminder message based on engagement state.
     */
    public function buildEngagementMessage(User $user, string $lang): string
    {
        $name    = $user->first_name ?? '';
        $isHindi = str_starts_with($lang, 'hi') || $lang === 'hi-roman';
        $state   = $user->engagement_state ?? 'active';

        if ($state === 'slow_response') {
            return $isHindi
                ? ($name ? "Hey {$name} 🌸 Kaisi chal rahi hai? Kuch poochna ho toh main yahan hoon." : "Hey 🌸 Kaisi chal rahi hai? Kuch poochna ho toh main yahan hoon.")
                : ($name ? "Hey {$name} 🌸 Just checking in — how are you doing?" : "Hey 🌸 Just checking in — how are you doing?");
        }

        // non_responsive
        return $isHindi
            ? ($name ? "Hey {$name} 💙 Aapki yaad aa rahi thi — sab theek hai na? Koi bhi baat share kar sakte hain." : "Hey 💙 Aapki yaad aa rahi thi — sab theek hai na?")
            : ($name ? "Hey {$name} 💙 I've been thinking about you — everything okay? I'm here whenever you need." : "Hey 💙 I've been thinking about you — everything okay?");
    }

    /**
     * Build the escalation voice call check-in message (warm, not alarming).
     */
    public function buildEscalationCallMessage(User $user, string $lang): string
    {
        $name    = $user->first_name ?? '';
        $isHindi = str_starts_with($lang, 'hi') || $lang === 'hi-roman';

        return $isHindi
            ? ($name ? "Namaste {$name}! Main Rakhi hoon — bas ek chhota sa check-in karne ke liye call kar rahi thi. Aap kaisa feel kar rahe hain?" : "Namaste! Main Rakhi hoon — bas check karne ke liye call kar rahi thi. Aap kaisa feel kar rahe hain?")
            : ($name ? "Hi {$name}! This is Rakhi — just calling to check in on you. How have you been feeling?" : "Hi! This is Rakhi — just calling to check in. How have you been feeling?");
    }
}
