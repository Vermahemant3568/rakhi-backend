<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Memory\MemoryManager;

class CleanupExpiredMemories extends Command
{
    protected $signature = 'memory:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up expired memories from database and vector store';

    public function handle()
    {
        $memoryManager = new MemoryManager();
        
        if ($this->option('dry-run')) {
            $this->info('Running in dry-run mode...');
            $this->info('This would clean up expired memories.');
            return;
        }

        $this->info('Starting memory cleanup...');
        
        try {
            $deletedCount = $memoryManager->cleanupExpiredMemories();
            
            $this->info("Successfully cleaned up {$deletedCount} expired memories.");
            
            if ($deletedCount > 0) {
                $this->line('Memory cleanup completed successfully.');
            } else {
                $this->line('No expired memories found to clean up.');
            }
            
        } catch (\Exception $e) {
            $this->error('Memory cleanup failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}