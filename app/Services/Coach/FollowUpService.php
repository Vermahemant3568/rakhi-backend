<?php

namespace App\Services\Coach;

class FollowUpService
{
    public function nextQuestion($user): ?string
    {
        $lastCheckin = $user->dailyCheckins()->latest()->first();

        if (!$lastCheckin) {
            return "Aaj subah aapne breakfast mein kya liya?";
        }

        if (!$lastCheckin->diet_followed) {
            return "Koi dikkat aayi diet follow karne mein?";
        }

        if (!$lastCheckin->activity_done) {
            return "Aaj exercise ka time nahi mila kya?";
        }

        if ($lastCheckin->mood === 'low') {
            return "Aaj mood thoda low lag raha hai, kya baat hai?";
        }

        if ($lastCheckin->energy === 'low') {
            return "Energy kam feel kar rahe hain? Neend poori hui thi?";
        }

        // Check if it's been more than 2 days since last checkin
        if ($lastCheckin->created_at->diffInDays(now()) > 2) {
            return "Kuch din se aapka update nahi aaya, sab theek hai na?";
        }

        return null;
    }

    public function getMotivationalMessage($user): string
    {
        $streak = $this->getCurrentStreak($user);
        
        if ($streak >= 7) {
            return "Waah! {$streak} din ka streak chal raha hai! Keep it up! 🔥";
        }

        if ($streak >= 3) {
            return "Great job! {$streak} din consistently kar rahe hain 👏";
        }

        return "Chalo ek nayi shururat karte hain! 💪";
    }

    private function getCurrentStreak($user): int
    {
        $streak = 0;
        $date = now()->startOfDay();

        while ($user->dailyCheckins()->whereDate('date', $date)->exists()) {
            $streak++;
            $date->subDay();
        }

        return $streak;
    }
}