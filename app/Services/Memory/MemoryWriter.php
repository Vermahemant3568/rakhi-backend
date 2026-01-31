<?php

namespace App\Services\Memory;

use App\Models\MemoryLog;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;
use Illuminate\Support\Str;

class MemoryWriter
{
    public function store(int $userId, string $type, string $text)
    {
        try {
            $embedding = (new EmbeddingService())->embed($text);
            $pineconeId = Str::uuid()->toString();

            (new PineconeService())->upsert(
                $pineconeId,
                $embedding,
                [
                    'user_id' => $userId,
                    'type' => $type
                ]
            );

            MemoryLog::create([
                'user_id' => $userId,
                'type' => $type,
                'summary' => $text,
                'pinecone_id' => $pineconeId
            ]);
        } catch (\Exception $e) {
            // Silently fail if Pinecone is not available
            // Memory will not be stored but app continues working
        }
    }
}