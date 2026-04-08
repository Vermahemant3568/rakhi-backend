<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Goal::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'icon'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'coach_id'    => 'nullable|exists:coaches,id',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);
        $data['slug'] = Str::slug($data['name']);
        return response()->json(Goal::create($data), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $goal = Goal::findOrFail($id);
        $data = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'icon'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'coach_id'    => 'nullable|exists:coaches,id',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);
        if (isset($data['name'])) $data['slug'] = Str::slug($data['name']);
        $goal->update($data);
        return response()->json($goal->fresh());
    }

    public function toggle($id): JsonResponse
    {
        $goal = Goal::findOrFail($id);
        $goal->update(['is_active' => !$goal->is_active]);
        return response()->json($goal->fresh());
    }

    public function destroy($id): JsonResponse
    {
        Goal::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
