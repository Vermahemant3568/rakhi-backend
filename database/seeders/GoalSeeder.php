<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoalSeeder extends Seeder
{
    public function run(): void
    {
        $goals = [
            ['name' => 'Lose Weight',           'sort_order' => 1],
            ['name' => 'Build Muscle',           'sort_order' => 2],
            ['name' => 'Manage Diabetes',        'sort_order' => 3],
            ['name' => 'Improve Sleep',          'sort_order' => 4],
            ['name' => 'Reduce Stress',          'sort_order' => 5],
            ['name' => 'Eat Healthier',          'sort_order' => 6],
            ['name' => 'Manage PCOS',            'sort_order' => 7],
            ['name' => 'Thyroid Management',     'sort_order' => 8],
            ['name' => 'Boost Energy',           'sort_order' => 9],
            ['name' => 'Pregnancy Wellness',     'sort_order' => 10],
            ['name' => 'Postpartum Recovery',    'sort_order' => 11],
            ['name' => 'Build Healthy Habits',   'sort_order' => 12],
            ['name' => 'Improve Mental Health',  'sort_order' => 13],
        ];

        foreach ($goals as $goal) {
            DB::table('goals')->insertOrIgnore(array_merge($goal, [
                'slug'       => Str::slug($goal['name']),
                'is_active'  => 1,
                'created_at' => now(),
            ]));
        }
    }
}
