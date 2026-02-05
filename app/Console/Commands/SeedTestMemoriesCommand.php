<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Memory\MemoryManager;
use App\Models\User;

class SeedTestMemoriesCommand extends Command
{
    protected $signature = 'rakhi:seed-memories {user_id}';
    protected $description = 'Seed test memories for a user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }
        
        $memoryManager = new MemoryManager();
        
        $testMemories = [
            ['type' => 'goal', 'content' => 'User wants to lose weight and get healthier'],
            ['type' => 'food', 'content' => 'User usually eats roti and dal for lunch'],
            ['type' => 'food', 'content' => 'User feels guilty after overeating'],
            ['type' => 'exercise', 'content' => 'User prefers morning walks over gym workouts'],
            ['type' => 'mood', 'content' => 'User gets stressed about work deadlines'],
            ['type' => 'habit', 'content' => 'User wants to drink more water daily'],
            ['type' => 'food', 'content' => 'User loves sweets but trying to control'],
            ['type' => 'goal', 'content' => 'User target is to lose 5kg in 3 months'],
        ];
        
        $this->info("Seeding test memories for user: {$user->name}");
        
        foreach ($testMemories as $memory) {
            $success = $memoryManager->storeMemory(
                $userId,
                $memory['type'],
                $memory['content'],
                ['seeded' => true]
            );
            
            if ($success) {
                $this->line("✓ Stored: {$memory['content']}");
            } else {
                $this->error("✗ Failed: {$memory['content']}");
            }
        }
        
        $this->info('Memory seeding completed!');
        return 0;
    }
}