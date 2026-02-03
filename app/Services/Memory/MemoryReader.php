<?php

namespace App\Services\Memory;

use App\Models\MemoryPolicy;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;

class MemoryReader
{
    public function recall(string $query, array $types = []): array
    {
        // Get active memory policies
        $activePolicies = MemoryPolicy::where('is_active', true)
            ->where('store_memory', true)
            ->when(!empty($types), function($q) use ($types) {
                return $q->whereIn('type', $types);
            })
            ->orderBy('priority', 'desc')
            ->get();
            
        if ($activePolicies->isEmpty()) {
            return [];
        }

        try {
            $vector = (new EmbeddingService())->embed($query);
            $result = (new PineconeService())->query($vector, [
                'filter' => [
                    'type' => ['$in' => $activePolicies->pluck('type')->toArray()]
                ]
            ]);
            return $result['matches'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
}