<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'icon', 'description', 'coach_id', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_goals')
                    ->wherePivot('is_active', 1);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
