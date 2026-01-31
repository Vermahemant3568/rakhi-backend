<?php

namespace App\Services\Memory;

use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;

class MemoryReader
{
    public function recall(string $query): array
    {
        try {
            $vector = (new EmbeddingService())->embed($query);
            $result = (new PineconeService())->query($vector);
            return $result['matches'] ?? [];
        } catch (\Exception $e) {
            // Return empty array if Pinecone is not available
            return [];
        }
    }
}