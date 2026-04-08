<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmConfig extends Model
{
    protected $fillable = [
        'provider', 'api_key', 'model_name', 'is_active',
        'max_tokens', 'temperature', 'top_p', 'extra_config',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'extra_config' => 'array',
        'temperature'  => 'float',
        'top_p'        => 'float',
    ];

    protected $hidden = [];

    protected $appends = ['has_api_key'];

    public function getHasApiKeyAttribute(): bool
    {
        return !empty($this->attributes['api_key']);
    }
}
