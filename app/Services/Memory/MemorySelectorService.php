<?php

namespace App\Services\Memory;

use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;
use App\Models\MemoryLog;
use App\Models\Goal;
use Illuminate\Support\Facades\Log;

class MemorySelectorService
{
    protected $embeddingService;
    protected $pineconeService;

    public function __construct()
    {
        $this->embeddingService = new EmbeddingService();
        $this->pineconeService = new PineconeService();
    }

    /**
     * Get relevant memories for user message
     */
    public function getRelevantMemories(int $userId, string $userMessage, int $limit = 5): array
    {
        try {
            // Create embedding for user message
            $queryVector = $this->embeddingService->embed($userMessage);
            
            if (empty($queryVector)) {
                Log::warning('Failed to create embedding for memory search', ['user_id' => $userId]);
                return [];
            }

            // Search Pinecone for relevant memories
            $searchResults = $this->pineconeService->query($queryVector, [
                'topK' => $limit * 2, // Get more to filter later
                'filter' => [
                    'user_id' => ['$eq' => $userId],
                    'expires_at' => ['$gt' => now()->toISOString()]
                ]
            ]);

            $memories = $this->processSearchResults($searchResults['matches'] ?? []);
            
            // Apply relevance scoring and priority filtering
            $scoredMemories = $this->scoreMemories($memories, $userMessage, $userId);
            
            // Return top memories
            return array_slice($scoredMemories, 0, $limit);
            
        } catch (\Exception $e) {
            Log::error('Memory selection failed', [
                'user_id' => $userId,
                'message' => $userMessage,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process Pinecone search results
     */
    protected function processSearchResults(array $matches): array
    {
        $memories = [];
        
        foreach ($matches as $match) {
            // Get memory content from database
            $memoryLog = MemoryLog::where('pinecone_id', $match['id'])->first();
            
            if ($memoryLog) {
                $memories[] = [
                    'id' => $match['id'],
                    'content' => $memoryLog->summary,
                    'type' => $match['metadata']['type'] ?? 'general',
                    'priority' => $match['metadata']['priority'] ?? 1,
                    'similarity_score' => $match['score'] ?? 0,
                    'created_at' => $match['metadata']['created_at'] ?? null,
                    'metadata' => $match['metadata'] ?? []
                ];
            }
        }
        
        return $memories;
    }

    /**
     * Score memories based on relevance and priority
     */
    protected function scoreMemories(array $memories, string $userMessage, int $userId): array
    {
        // Get user goals for priority scoring
        $userGoals = Goal::whereHas('users', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->pluck('name')->toArray();

        foreach ($memories as &$memory) {
            $finalScore = $this->calculateFinalScore($memory, $userMessage, $userGoals);
            $memory['final_score'] = $finalScore;
        }

        // Sort by final score (highest first)
        usort($memories, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });

        return $memories;
    }

    /**
     * Calculate final relevance score
     */
    protected function calculateFinalScore(array $memory, string $userMessage, array $userGoals): float
    {
        $score = $memory['similarity_score'];
        
        // Boost score based on memory priority
        $priorityBoost = $memory['priority'] * 0.1;
        $score += $priorityBoost;
        
        // Boost goal-related memories
        if ($this->isGoalRelated($memory, $userGoals)) {
            $score += 0.15;
        }
        
        // Boost recent memories slightly
        if ($this->isRecentMemory($memory)) {
            $score += 0.05;
        }
        
        // Boost memories that match message intent
        if ($this->matchesIntent($memory, $userMessage)) {
            $score += 0.1;
        }
        
        return $score;
    }

    /**
     * Check if memory is related to user goals
     */
    protected function isGoalRelated(array $memory, array $userGoals): bool
    {
        $memoryContent = strtolower($memory['content']);
        
        foreach ($userGoals as $goal) {
            if (str_contains($memoryContent, strtolower($goal))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if memory is recent (within last 7 days)
     */
    protected function isRecentMemory(array $memory): bool
    {
        if (!$memory['created_at']) {
            return false;
        }
        
        $createdAt = \Carbon\Carbon::parse($memory['created_at']);
        return $createdAt->diffInDays(now()) <= 7;
    }

    /**
     * Check if memory matches message intent
     */
    protected function matchesIntent(array $memory, string $userMessage): bool
    {
        $memoryType = $memory['type'];
        $messageLower = strtolower($userMessage);
        
        // Simple intent matching
        $intentMap = [
            'food' => ['eat', 'food', 'meal', 'lunch', 'dinner', 'breakfast', 'hungry'],
            'exercise' => ['workout', 'exercise', 'gym', 'run', 'walk', 'fitness'],
            'mood' => ['feel', 'mood', 'sad', 'happy', 'angry', 'stressed'],
            'goal' => ['goal', 'target', 'achieve', 'progress', 'success'],
            'habit' => ['habit', 'routine', 'daily', 'regular', 'practice']
        ];
        
        if (isset($intentMap[$memoryType])) {
            foreach ($intentMap[$memoryType] as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Format memories for prompt injection
     */
    public function formatMemoriesForPrompt(array $memories): string
    {
        if (empty($memories)) {
            return '';
        }

        $formattedMemories = [];
        
        foreach ($memories as $memory) {
            $formattedMemories[] = "- {$memory['content']} (Type: {$memory['type']})";
        }
        
        return "RELEVANT MEMORIES:\n" . implode("\n", $formattedMemories) . "\n\n";
    }
}