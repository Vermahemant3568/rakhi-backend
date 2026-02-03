<?php

namespace App\Services\Memory;

use App\Models\MemoryLog;
use App\Models\MemoryPolicy;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;
use Illuminate\Support\Str;

class MemoryWriter
{
    public function store(int $userId, string $type, string $text)
    {
        // Check if memory storage is allowed for this type
        $policy = MemoryPolicy::where('type', $type)
            ->where('is_active', true)
            ->first();
            
        if (!$policy || !$policy->store_memory) {
            return;
        }

        try {
            $embedding = (new EmbeddingService())->embed($text);
            $pineconeId = Str::uuid()->toString();

            (new PineconeService())->upsert(
                $pineconeId,
                $embedding,
                [
                    'user_id' => $userId,
                    'type' => $type,
                    'priority' => $policy->priority
                ]
            );

            MemoryLog::create([
                'user_id' => $userId,
                'type' => $type,
                'summary' => $text,
                'pinecone_id' => $pineconeId
            ]);
        } catch (\Exception $e) {
            // Silently fail if services unavailable
        }
    }
}