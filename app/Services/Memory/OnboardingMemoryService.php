<?php

namespace App\Services\Memory;

use App\Services\Memory\MemoryManager;

class OnboardingMemoryService
{
    protected $memoryManager;

    public function __construct()
    {
        $this->memoryManager = new MemoryManager();
    }

    public function storeOnboardingData($user)
    {
        try {
            $profile = $user->profile;
            $goals = $user->goals;
            
            if ($profile) {
                // Store profile information
                $profileMemory = "User profile: Name is {$profile->first_name}";
                if ($profile->gender) {
                    $profileMemory .= ", gender is {$profile->gender}";
                }
                if ($profile->dob) {
                    $age = now()->diffInYears($profile->dob);
                    $profileMemory .= ", age is {$age} years";
                }
                
                $this->memoryManager->storeMemory(
                    $user->id,
                    'profile',
                    $profileMemory,
                    ['source' => 'onboarding', 'priority' => 'high']
                );
            }
            
            if ($goals->isNotEmpty()) {
                // Store goals information
                $goalsList = $goals->pluck('title')->join(', ');
                $goalMemory = "User's health goals: {$goalsList}";
                
                $this->memoryManager->storeMemory(
                    $user->id,
                    'goal',
                    $goalMemory,
                    ['source' => 'onboarding', 'priority' => 'high']
                );
            }
            
        } catch (\Exception $e) {
            \Log::warning('Failed to store onboarding memories: ' . $e->getMessage());
        }
    }
}