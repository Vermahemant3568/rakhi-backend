<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RakhiRule extends Model
{
    protected $fillable = [
        'rule_type', 'title', 'rule_content',
        'applies_to_coaches', 'is_active', 'priority',
    ];

    protected $casts = [
        'applies_to_coaches' => 'array',
        'is_active'          => 'boolean',
    ];
}
