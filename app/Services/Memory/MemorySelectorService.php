<?php

namespace App\Services\Memory;

class MemorySelectorService
{
    public function __construct(
        private MemoryManager $memoryManager
    ) {}

    public function getRelevantMemories(int $userId, string $query, int $limit = 5): array
    {
        return $this->memoryManager->recallMemories($userId, $query, [], $limit);
    }

    public function select(array $matches): string
    {
        $context = [];

        foreach ($matches as $match) {
            if (($match['score'] ?? 0) < 0.75) {
                continue;
            }

            if (isset($match['metadata']['summary'])) {
                $context[] = $match['metadata']['summary'];
            }
        }

        return implode("\n", $context);
    }
}