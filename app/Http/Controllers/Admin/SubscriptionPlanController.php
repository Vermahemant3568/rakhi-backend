<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SubscriptionPlan::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:150',
            'duration_days'     => 'required|integer',
            'price'             => 'required|numeric',
            'discounted_price'  => 'nullable|numeric',
            'trial_days'        => 'integer',
            'features'          => 'nullable|array',
            'is_active'         => 'boolean',
            'sort_order'        => 'integer',
        ]);
        return response()->json(SubscriptionPlan::create($data), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $data = $request->validate([
            'name'              => 'sometimes|string|max:150',
            'duration_days'     => 'sometimes|integer',
            'price'             => 'sometimes|numeric',
            'discounted_price'  => 'nullable|numeric',
            'trial_days'        => 'integer',
            'features'          => 'nullable|array',
            'is_active'         => 'boolean',
            'sort_order'        => 'integer',
        ]);
        $plan->update($data);
        return response()->json($plan->fresh());
    }

    public function toggle($id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);
        return response()->json($plan->fresh());
    }
}
