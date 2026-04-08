<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coach extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'speciality',
        'pinecone_namespace', 'system_prompt_key',
        'is_launch_coach', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_launch_coach' => 'boolean',
    ];
}
