<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'resource',
        'details',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'details' => 'array'
    ];

    public function admin()
    {
        return $this->belongsTo(AdminUser::class);
    }
}