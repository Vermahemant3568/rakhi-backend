<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_base';

    protected $fillable = [
        'coach_id', 'title', 'content', 'pinecone_vector_id',
        'pinecone_namespace', 'source_file', 'file_type',
        'is_synced', 'is_active',
    ];

    protected $casts = [
        'is_synced' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
