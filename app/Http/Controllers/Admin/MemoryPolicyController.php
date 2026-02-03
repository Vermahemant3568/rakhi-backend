<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemoryPolicy;
use Illuminate\Http\Request;

class MemoryPolicyController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(MemoryPolicy::orderBy('priority', 'desc')->get());
        }
        return view('admin.ai-control.memory-policies');
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|unique:memory_policies,type',
            'description' => 'nullable|string',
            'priority' => 'required|integer|min:1|max:10',
            'store_memory' => 'required|boolean',
            'is_active' => 'required|boolean',
            'retention_days' => 'required|integer|min:1|max:3650'
        ]);

        $policy = MemoryPolicy::create($request->all());

        return response()->json([
            'message' => 'Memory policy created successfully',
            'policy' => $policy
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'priority' => 'required|integer|min:1|max:10',
            'store_memory' => 'required|boolean',
            'is_active' => 'required|boolean',
            'retention_days' => 'required|integer|min:1|max:3650',
            'description' => 'nullable|string'
        ]);

        $policy = MemoryPolicy::findOrFail($id);
        $policy->update($request->only(['priority', 'store_memory', 'is_active', 'retention_days', 'description']));

        return response()->json([
            'message' => 'Policy updated successfully',
            'policy' => $policy
        ]);
    }

    public function toggle($id)
    {
        $policy = MemoryPolicy::findOrFail($id);
        $policy->update(['is_active' => !$policy->is_active]);

        return response()->json([
            'message' => 'Policy status toggled successfully',
            'policy' => $policy
        ]);
    }

    public function toggleStorage($id)
    {
        $policy = MemoryPolicy::findOrFail($id);
        $policy->update(['store_memory' => !$policy->store_memory]);

        return response()->json([
            'message' => 'Memory storage toggled successfully',
            'policy' => $policy
        ]);
    }

    public function initializeDefaults()
    {
        $defaultPolicies = MemoryPolicy::getDefaultPolicies();
        
        foreach ($defaultPolicies as $policyData) {
            MemoryPolicy::updateOrCreate(
                ['type' => $policyData['type']],
                $policyData
            );
        }

        return response()->json([
            'message' => 'Default memory policies initialized successfully'
        ]);
    }

    public function stats()
    {
        $stats = [
            'total_policies' => MemoryPolicy::count(),
            'active_policies' => MemoryPolicy::where('is_active', true)->count(),
            'storing_policies' => MemoryPolicy::where('store_memory', true)->count(),
            'high_priority' => MemoryPolicy::where('priority', '>=', 8)->count(),
            'avg_retention' => MemoryPolicy::avg('retention_days')
        ];

        return response()->json($stats);
    }
}