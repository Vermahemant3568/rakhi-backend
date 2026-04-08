<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'code', 'native_name', 'tts_code', 'stt_code', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];
}
