<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RakhiRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RulesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RakhiRule::query();

        if ($request->filled('rule_type')) {
            $query->where('rule_type', $request->rule_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return response()->json($query->orderBy('priority', 'desc')->orderBy('rule_type')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rule_type'          => 'required|string|max:100',
            'title'              => 'required|string|max:200',
            'rule_content'       => 'required|string',
            'applies_to_coaches' => 'nullable|array',
            'applies_to_coaches.*' => 'exists:coaches,id',
            'is_active'          => 'boolean',
            'priority'           => 'integer',
        ]);

        return response()->json(RakhiRule::create($data), 201);
    }

    public function update(Request $request, RakhiRule $rule): JsonResponse
    {
        $data = $request->validate([
            'rule_type'          => 'sometimes|string|max:100',
            'title'              => 'sometimes|string|max:200',
            'rule_content'       => 'sometimes|string',
            'applies_to_coaches' => 'nullable|array',
            'applies_to_coaches.*' => 'exists:coaches,id',
            'is_active'          => 'boolean',
            'priority'           => 'integer',
        ]);

        $rule->update($data);

        return response()->json($rule->fresh());
    }

    public function toggle(RakhiRule $rule): JsonResponse
    {
        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json($rule->fresh());
    }

    public function destroy(RakhiRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
