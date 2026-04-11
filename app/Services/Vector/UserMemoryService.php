<?php

namespace App\Services\Vector;

use App\Models\User;
use App\Services\AI\EmbeddingService;

class UserMemoryService
{
    public function __construct(
        private PineconeService $pinecone,
        private EmbeddingService $embedder
    ) {}

    public function store(
        User $user,
        string $message,
        string $role,
        array $metadata = []
    ): void {
        // Skip empty messages
        if (empty(trim($message))) return;

        $vector    = $this->embedder->embed($message);

        // If embedding returned empty (quota hit), skip silently
        if (empty($vector)) return;

        $namespace = "user-{$user->id}";
        $id        = "msg-" . $user->id . "-" . time() . "-" . rand(100, 999);

        $this->pinecone->upsert(
            namespace: $namespace,
            id: $id,
            vector: $vector,
            metadata: array_merge([
                'user_id' => $user->id,
                'role'    => $role,
                'message' => substr($message, 0, 500),
                'date'    => now()->toDateString(),
            ], $metadata)
        );
    }

    public function recall(User $user, string $query, int $limit = 5): array
    {
        $vector = $this->embedder->embed($query);

        // If embedding failed, return empty gracefully
        if (empty($vector)) return [];

        $namespace = "user-{$user->id}";

        $matches = $this->pinecone->query(
            namespace: $namespace,
            vector: $vector,
            topK: $limit
        );

        return array_map(
            fn($m) => $m['metadata']['message'] ?? '',
            $matches
        );
    }

    public function recallCoachKnowledge(
        string $coachNamespace,
        string $query,
        int $limit = 5
    ): array {
        $vector = $this->embedder->embed($query);

        // If embedding failed, return empty gracefully
        if (empty($vector)) return [];

        $matches = $this->pinecone->query(
            namespace: $coachNamespace,
            vector: $vector,
            topK: $limit
        );

        return array_map(
            fn($m) => $m['metadata']['title'] . ': ' .
                      ($m['metadata']['message'] ?? ''),
            $matches
        );
    }
}
