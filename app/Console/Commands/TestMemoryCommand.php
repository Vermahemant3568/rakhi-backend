<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Memory\MemoryManager;
use App\Services\Memory\MemorySelectorService;
use App\Models\User;

class TestMemoryCommand extends Command
{
    protected $signature = 'rakhi:test-memory {user_id} {message}';
    protected $description = 'Test memory injection for a user message';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $message = $this->argument('message');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }
        
        $this->info("Testing memory for user: {$user->name}");
        $this->info("Message: {$message}");
        $this->line('');
        
        // Test memory selection
        $memorySelectorService = new MemorySelectorService();
        $memories = $memorySelectorService->getRelevantMemories($userId, $message, 5);
        
        if (empty($memories)) {
            $this->warn('No relevant memories found');
        } else {
            $this->info('Found ' . count($memories) . ' relevant memories:');
            foreach ($memories as $memory) {
                $this->line("- Score: {$memory['final_score']:.3f} | Type: {$memory['type']} | Content: {$memory['content']}");
            }
        }
        
        $this->line('');
        
        // Show formatted memory context
        $memoryContext = $memorySelectorService->formatMemoriesForPrompt($memories);
        if ($memoryContext) {
            $this->info('Memory context for prompt:');
            $this->line($memoryContext);
        }
        
        return 0;
    }
}