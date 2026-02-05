<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryPolicy extends Model
{
    protected $fillable = [
        'type',
        'description',
        'is_active',
        'priority',
        'retention_days',
        'store_memory'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'store_memory' => 'boolean'
    ];

    public static function getDefaultPolicies(): array
    {
        return [
            [
                'type' => 'profile',
                'description' => 'User profile and personal information',
                'is_active' => true,
                'priority' => 10,
                'retention_days' => 365,
                'store_memory' => true
            ],
            [
                'type' => 'goal',
                'description' => 'User health and fitness goals',
                'is_active' => true,
                'priority' => 9,
                'retention_days' => 180,
                'store_memory' => true
            ],
            [
                'type' => 'food',
                'description' => 'Food intake and meal logging',
                'is_active' => true,
                'priority' => 7,
                'retention_days' => 30,
                'store_memory' => true
            ],
            [
                'type' => 'exercise',
                'description' => 'Exercise and physical activity',
                'is_active' => true,
                'priority' => 6,
                'retention_days' => 30,
                'store_memory' => true
            ],
            [
                'type' => 'mood',
                'description' => 'Emotional state and feelings',
                'is_active' => true,
                'priority' => 5,
                'retention_days' => 14,
                'store_memory' => true
            ],
            [
                'type' => 'habit',
                'description' => 'Daily habits and routines',
                'is_active' => true,
                'priority' => 4,
                'retention_days' => 60,
                'store_memory' => true
            ],
            [
                'type' => 'general',
                'description' => 'General conversation and interactions',
                'is_active' => true,
                'priority' => 3,
                'retention_days' => 7,
                'store_memory' => true
            ]
        ];
    }
}