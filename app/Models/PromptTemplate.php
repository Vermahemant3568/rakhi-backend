<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = [
        'coach_id', 'language_id', 'template_type',
        'title', 'content', 'variables', 'is_active', 'version',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
