<?php

namespace App\Services\Coach;

use App\Models\Message;
use App\Models\MessageAttachment;
use Carbon\Carbon;

class HabitMemoryService
{
    public function analyzePatterns($user): array
    {
        $recentDays = 7;
        $patterns = [];
        
        // 🧠 3️⃣ Habit Memory - Analyze meal photo patterns
        $mealPhotos = $this->getMealPhotoCount($user, $recentDays);
        if ($mealPhotos >= 5) {
            $patterns[] = [
                'type' => 'consistency',
                'message' => 'Main dekh rahi hoon ki aap regularly meal photos share kar rahe ho — great consistency 👏',
                'confidence' => 'high'
            ];
        }
        
        // Analyze meal timing patterns
        $timingPattern = $this->analyzeMealTiming($user, $recentDays);
        if ($timingPattern) {
            $patterns[] = $timingPattern;
        }
        
        // Check for improvement trends
        $improvement = $this->checkImprovementTrend($user, $recentDays);
        if ($improvement) {
            $patterns[] = $improvement;
        }
        
        return $patterns;
    }
    
    private function getMealPhotoCount($user, int $days): int
    {
        return Message::where('conversation_id', function($query) use ($user) {
                $query->select('id')
                      ->from('conversations')
                      ->where('user_id', $user->id)
                      ->where('status', 'active');
            })
            ->whereHas('attachments', function($query) {
                $query->where('type', 'image');
            })
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->count();
    }
    
    private function analyzeMealTiming($user, int $days): ?array
    {
        // Simple pattern: if user sends photos mostly during lunch hours
        $lunchTimePhotos = Message::where('conversation_id', function($query) use ($user) {
                $query->select('id')
                      ->from('conversations')
                      ->where('user_id', $user->id);
            })
            ->whereHas('attachments')
            ->whereTime('created_at', '>=', '12:00:00')
            ->whereTime('created_at', '<=', '14:00:00')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->count();
            
        if ($lunchTimePhotos >= 3) {
            return [
                'type' => 'timing',
                'message' => 'Aap lunch time pe consistently photos share karte hain — good habit! 🕐',
                'confidence' => 'medium'
            ];
        }
        
        return null;
    }
    
    private function checkImprovementTrend($user, int $days): ?array
    {
        // Check if user is asking more health-conscious questions
        $healthQuestions = Message::where('conversation_id', function($query) use ($user) {
                $query->select('id')
                      ->from('conversations')
                      ->where('user_id', $user->id);
            })
            ->where('sender', 'user')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->where(function($query) {
                $query->where('message', 'like', '%healthy%')
                      ->orWhere('message', 'like', '%protein%')
                      ->orWhere('message', 'like', '%nutrition%');
            })
            ->count();
            
        if ($healthQuestions >= 2) {
            return [
                'type' => 'improvement',
                'message' => 'Mujhe lag raha hai aap health ke baare mein zyada conscious ho rahe hain — that\'s wonderful! 🌟',
                'confidence' => 'medium'
            ];
        }
        
        return null;
    }
}