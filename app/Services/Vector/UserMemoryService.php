<?php

namespace App\Services\Vector;

use App\Models\User;
use App\Models\UserMemory;
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
        if (empty(trim($message))) return;

        $vector = $this->embedder->embed($message);
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
        if (empty($vector)) return [];

        $matches = $this->pinecone->query(
            namespace: "user-{$user->id}",
            vector: $vector,
            topK: $limit
        );

        // Filter low-relevance matches (score < 0.75)
        return array_values(array_map(
            fn($m) => $m['metadata']['message'] ?? '',
            array_filter($matches, fn($m) => ($m['score'] ?? 0) >= 0.75)
        ));
    }

    public function recallCoachKnowledge(
        string $coachNamespace,
        string $query,
        int $limit = 5
    ): array {
        $vector = $this->embedder->embed($query);
        if (empty($vector)) return [];

        $matches = $this->pinecone->query(
            namespace: $coachNamespace,
            vector: $vector,
            topK: $limit
        );

        return array_map(
            fn($m) => $m['metadata']['title'] . ': ' . ($m['metadata']['message'] ?? ''),
            $matches
        );
    }

    /**
     * Retrieve all structured memory facts for a user from DB.
     * Returns a flat key => value array.
     */
    public function getStructuredMemory(User $user): array
    {
        return UserMemory::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Check if a specific memory key exists for the user.
     */
    public function hasMemory(User $user, string $key): bool
    {
        return UserMemory::where('user_id', $user->id)
            ->where('key', $key)
            ->exists();
    }
}
