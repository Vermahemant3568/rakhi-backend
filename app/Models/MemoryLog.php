<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'summary',
        'pinecone_id'
    ];
}
