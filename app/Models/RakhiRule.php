<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RakhiRule extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_active'
    ];
}
