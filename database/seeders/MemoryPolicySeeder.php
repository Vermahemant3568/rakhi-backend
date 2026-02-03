<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MemoryPolicy;

class MemoryPolicySeeder extends Seeder
{
    public function run(): void
    {
        $defaultPolicies = MemoryPolicy::getDefaultPolicies();
        
        foreach ($defaultPolicies as $policyData) {
            MemoryPolicy::updateOrCreate(
                ['type' => $policyData['type']],
                $policyData
            );
        }
    }
}