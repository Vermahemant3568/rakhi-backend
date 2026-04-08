<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoachSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = [
            ['name' => 'Diabetes Coach',        'pinecone_namespace' => 'coach-diabetes',        'speciality' => 'Diabetes Management',       'is_launch_coach' => 1, 'sort_order' => 1],
            ['name' => 'Diet & Nutrition Coach', 'pinecone_namespace' => 'coach-diet-nutrition',  'speciality' => 'Diet & Nutrition',           'is_launch_coach' => 1, 'sort_order' => 2],
            ['name' => 'Fitness Coach',          'pinecone_namespace' => 'coach-fitness',         'speciality' => 'Fitness & Exercise',         'is_launch_coach' => 1, 'sort_order' => 3],
            ['name' => 'PCOS & Thyroid Coach',   'pinecone_namespace' => 'coach-pcos-thyroid',    'speciality' => 'PCOS & Thyroid Health',      'is_launch_coach' => 1, 'sort_order' => 4],
            ['name' => 'Mental Wellness Coach',  'pinecone_namespace' => 'coach-mental-wellness', 'speciality' => 'Mental Health & Wellness',   'is_launch_coach' => 1, 'sort_order' => 5],
            ['name' => 'Sleep Coach',            'pinecone_namespace' => 'coach-sleep',           'speciality' => 'Sleep Optimization',         'is_launch_coach' => 0, 'sort_order' => 6],
            ['name' => 'Weight Loss Coach',      'pinecone_namespace' => 'coach-weight-loss',     'speciality' => 'Weight Management',          'is_launch_coach' => 1, 'sort_order' => 7],
            ['name' => 'Pregnancy Coach',        'pinecone_namespace' => 'coach-pregnancy',       'speciality' => 'Pregnancy & Prenatal Care',  'is_launch_coach' => 0, 'sort_order' => 8],
            ['name' => 'Postpartum Coach',       'pinecone_namespace' => 'coach-postpartum',      'speciality' => 'Postpartum Recovery',        'is_launch_coach' => 0, 'sort_order' => 9],
            ['name' => 'Energy Coach',           'pinecone_namespace' => 'coach-energy',          'speciality' => 'Energy & Vitality',          'is_launch_coach' => 0, 'sort_order' => 10],
            ['name' => 'Stress Coach',           'pinecone_namespace' => 'coach-stress',          'speciality' => 'Stress Management',          'is_launch_coach' => 0, 'sort_order' => 11],
            ['name' => 'Habit Coach',            'pinecone_namespace' => 'coach-habit',           'speciality' => 'Habit Building',             'is_launch_coach' => 0, 'sort_order' => 12],
            ['name' => 'Vision Coach',            'pinecone_namespace' => 'coach-vision',          'speciality' => 'Eye Health & Vision Wellness', 'is_launch_coach' => 0, 'sort_order' => 13],
        ];

        foreach ($coaches as $coach) {
            DB::table('coaches')->insertOrIgnore(array_merge($coach, [
                'slug'       => Str::slug($coach['name']),
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
