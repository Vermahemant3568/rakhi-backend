<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPlan extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'plan_type', 'coach_id',
        'session_id', 'file_url', 'plan_data', 'generated_at',
    ];

    protected $casts = [
        'plan_data'    => 'array',
        'generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
