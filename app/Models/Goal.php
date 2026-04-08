<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'icon', 'description', 'coach_id', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];
}
