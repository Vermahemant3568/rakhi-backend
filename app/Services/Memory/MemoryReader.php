<?php

namespace App\Services\Memory;

use App\Models\MemoryPolicy;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;

class MemoryReader
{
    public function recall(string $query, int $userId): array
    {
        $vector = (new EmbeddingService())->embed($query);

        $response = (new PineconeService())->query($vector);

        // Filter only this user's memories
        return collect($response['matches'] ?? [])
            ->filter(fn($m) => $m['metadata']['user_id'] == $userId)
            ->values()
            ->toArray();
    }
}