<?php

namespace App\Services\Memory;

use App\Models\MemoryLog;
use App\Models\MemoryPolicy;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MemoryManager
{
    protected $embeddingService;
    protected $pineconeService;
    protected $memoryWriter;
    protected $memoryReader;

    public function __construct()
    {
        $this->embeddingService = new EmbeddingService();
        $this->pineconeService = new PineconeService();
        $this->memoryWriter = new MemoryWriter();
        $this->memoryReader = new MemoryReader();
    }

    /**
     * Store memory based on active policies
     */
    public function storeMemory(int $userId, string $type, string $content, array $metadata = [])
    {
        $policy = $this->getActivePolicy($type);
        
        if (!$policy || !$policy->store_memory) {
            return false;
        }

        try {
            $embedding = $this->embeddingService->embed($content);
            $pineconeId = Str::uuid()->toString();

            $vectorMetadata = array_merge([
                'user_id' => $userId,
                'type' => $type,
                'priority' => $policy->priority,
                'created_at' => now()->toISOString(),
                'expires_at' => now()->addDays($policy->retention_days)->toISOString()
            ], $metadata);

            $this->pineconeService->upsert($pineconeId, $embedding, $vectorMetadata);

            MemoryLog::create([
                'user_id' => $userId,
                'type' => $type,
                'summary' => $content,
                'pinecone_id' => $pineconeId,
                'expires_at' => now()->addDays($policy->retention_days)
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Memory storage failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Recall memories based on query and active policies
     */
    public function recallMemories(int $userId, string $query, array $types = [], int $limit = 5)
    {
        $activePolicies = $this->getActivePolicies($types);
        
        if ($activePolicies->isEmpty()) {
            return [];
        }

        try {
            $vector = $this->embeddingService->embed($query);
            
            $result = $this->pineconeService->query($vector, [
                'topK' => $limit,
                'filter' => [
                    'user_id' => ['$eq' => $userId],
                    'type' => ['$in' => $activePolicies->pluck('type')->toArray()],
                    'expires_at' => ['$gt' => now()->toISOString()]
                ]
            ]);

            return $this->processMemoryResults($result['matches'] ?? []);
        } catch (\Exception $e) {
            \Log::error('Memory recall failed', [
                'user_id' => $userId,
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get memory statistics for a user
     */
    public function getUserMemoryStats(int $userId)
    {
        $stats = [
            'total_memories' => MemoryLog::where('user_id', $userId)->count(),
            'active_memories' => MemoryLog::where('user_id', $userId)
                ->where('expires_at', '>', now())
                ->count(),
            'expired_memories' => MemoryLog::where('user_id', $userId)
                ->where('expires_at', '<=', now())
                ->count(),
            'memories_by_type' => MemoryLog::where('user_id', $userId)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray()
        ];

        return $stats;
    }

    /**
     * Clean up expired memories
     */
    public function cleanupExpiredMemories()
    {
        $expiredMemories = MemoryLog::where('expires_at', '<=', now())->get();
        
        foreach ($expiredMemories as $memory) {
            try {
                // Delete from Pinecone
                $this->pineconeService->delete($memory->pinecone_id);
                
                // Delete from database
                $memory->delete();
            } catch (\Exception $e) {
                \Log::error('Failed to cleanup memory', [
                    'memory_id' => $memory->id,
                    'pinecone_id' => $memory->pinecone_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $expiredMemories->count();
    }

    /**
     * Update memory retention based on policy changes
     */
    public function updateMemoryRetention(string $type, int $newRetentionDays)
    {
        $policy = MemoryPolicy::where('type', $type)->first();
        
        if (!$policy) {
            return false;
        }

        // Update policy
        $policy->update(['retention_days' => $newRetentionDays]);

        // Update existing memories of this type
        $memories = MemoryLog::where('type', $type)->get();
        
        foreach ($memories as $memory) {
            $newExpiryDate = $memory->created_at->addDays($newRetentionDays);
            $memory->update(['expires_at' => $newExpiryDate]);
        }

        return $memories->count();
    }

    /**
     * Get active policy for a memory type
     */
    protected function getActivePolicy(string $type)
    {
        return MemoryPolicy::where('type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active policies, optionally filtered by types
     */
    protected function getActivePolicies(array $types = [])
    {
        return MemoryPolicy::where('is_active', true)
            ->where('store_memory', true)
            ->when(!empty($types), function($q) use ($types) {
                return $q->whereIn('type', $types);
            })
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Process and format memory results
     */
    protected function processMemoryResults(array $matches)
    {
        $results = [];
        
        foreach ($matches as $match) {
            $results[] = [
                'id' => $match['id'],
                'score' => $match['score'],
                'type' => $match['metadata']['type'] ?? 'unknown',
                'priority' => $match['metadata']['priority'] ?? 1,
                'created_at' => $match['metadata']['created_at'] ?? null,
                'content' => $this->getMemoryContent($match['id'])
            ];
        }

        // Sort by priority and score
        usort($results, function($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return $b['score'] <=> $a['score'];
            }
            return $b['priority'] <=> $a['priority'];
        });

        return $results;
    }

    /**
     * Get memory content from database
     */
    protected function getMemoryContent(string $pineconeId)
    {
        $memory = MemoryLog::where('pinecone_id', $pineconeId)->first();
        return $memory ? $memory->summary : null;
    }
}