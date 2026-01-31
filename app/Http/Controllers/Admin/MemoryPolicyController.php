<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemoryPolicy;
use Illuminate\Http\Request;

class MemoryPolicyController extends Controller
{
    public function index()
    {
        if (request()->expectsJson()) {
            $policies = MemoryPolicy::all();
            return response()->json($policies);
        }
        
        return view('admin.ai-control.memory-policies');
    }

    public function update(Request $request, MemoryPolicy $policy)
    {
        $policy->update($request->only(['store', 'priority']));
        return response()->json($policy);
    }

    public function toggle(MemoryPolicy $policy)
    {
        $policy->update(['store' => !$policy->store]);
        return response()->json($policy);
    }

    public function store(Request $request)
    {
        $policy = MemoryPolicy::create($request->only(['type', 'store', 'priority']));
        return response()->json($policy);
    }
}
