<?php

namespace App\Jobs;

use App\Models\KnowledgeBase;
use App\Services\Vector\PineconeService;
use App\Services\AI\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncKnowledgeToVector implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public KnowledgeBase $knowledge) {}

    public function handle(PineconeService $pinecone, EmbeddingService $embedding): void
    {
        try {
            $vector = $embedding->embed($this->knowledge->content);

            $namespace = $this->knowledge->pinecone_namespace
                ?? $this->knowledge->coach->pinecone_namespace
                ?? 'coach-' . $this->knowledge->coach_id;

            $vectorId = 'kb-' . $this->knowledge->id;

            $pinecone->upsert(
                namespace: $namespace,
                id:        $vectorId,
                vector:    $vector,
                metadata:  [
                    'knowledge_id' => $this->knowledge->id,
                    'coach_id'     => $this->knowledge->coach_id,
                    'title'        => $this->knowledge->title,
                    'message'      => substr($this->knowledge->content, 0, 500),
                    'file_type'    => $this->knowledge->file_type,
                ]
            );

            $this->knowledge->update([
                'is_synced'          => 1,
                'pinecone_vector_id' => $vectorId,
                'pinecone_namespace' => $namespace,
            ]);

        } catch (\Throwable $e) {
            Log::error('SyncKnowledgeToVector failed', [
                'knowledge_id' => $this->knowledge->id,
                'error'        => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
