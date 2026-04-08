<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Jobs\SyncKnowledgeToVector;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private EmbeddingService $embedder,
        private PineconeService $pinecone,
    ) {}
    public function index(Request $request)
    {
        $query = KnowledgeBase::with('coach');
        if ($request->coach_id) {
            $query->where('coach_id', $request->coach_id);
        }
        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'coach_id' => 'required|exists:coaches,id',
            'title'    => 'required|string',
            'content'  => 'required|string',
        ]);

        $kb = KnowledgeBase::create([
            'coach_id'  => $request->coach_id,
            'title'     => $request->title,
            'content'   => $request->content,
            'file_type' => 'text',
            'is_synced' => 0,
            'is_active' => 1,
        ]);

        $this->syncToPinecone($kb);

        return response()->json(['message' => 'Knowledge added and synced to Pinecone', 'kb' => $kb->fresh()]);
    }

    public function syncToVector($id)
    {
        $kb = KnowledgeBase::findOrFail($id);
        $this->syncToPinecone($kb);
        return response()->json(['message' => 'Synced to Pinecone', 'kb' => $kb->fresh()]);
    }

    public function update(Request $request, $id)
    {
        $kb = KnowledgeBase::findOrFail($id);
        $kb->update(array_merge($request->only(['title', 'content']), ['is_synced' => 0]));
        $this->syncToPinecone($kb->fresh());
        return response()->json(['message' => 'Updated and synced to Pinecone', 'kb' => $kb->fresh()]);
    }

    public function toggle($id)
    {
        $kb = KnowledgeBase::findOrFail($id);
        $kb->update(['is_active' => !$kb->is_active]);
        return response()->json(['message' => 'Toggled']);
    }

    public function destroy($id)
    {
        KnowledgeBase::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function syncToPinecone(KnowledgeBase $kb): void
    {
        try {
            $vector    = $this->embedder->embed($kb->title . ' ' . $kb->content);
            $namespace = $kb->pinecone_namespace ?? $kb->coach->pinecone_namespace;
            $vectorId  = 'kb-' . $kb->id;

            $this->pinecone->upsert(
                namespace: $namespace,
                id:        $vectorId,
                vector:    $vector,
                metadata:  [
                    'knowledge_id' => $kb->id,
                    'coach_id'     => $kb->coach_id,
                    'title'        => $kb->title,
                    'message'      => substr($kb->content, 0, 500),
                    'file_type'    => $kb->file_type,
                ]
            );

            $kb->update([
                'is_synced'          => 1,
                'pinecone_vector_id' => $vectorId,
                'pinecone_namespace' => $namespace,
            ]);
        } catch (\Throwable $e) {
            Log::error('Pinecone sync failed: ' . $e->getMessage());
        }
    }
}
