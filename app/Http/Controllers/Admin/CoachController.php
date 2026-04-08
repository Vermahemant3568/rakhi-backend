<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoachController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Coach::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',
            'speciality'          => 'nullable|string|max:255',
            'pinecone_namespace'  => 'nullable|string|max:100',
            'system_prompt_key'   => 'nullable|string|max:100',
            'is_launch_coach'     => 'boolean',
            'is_active'           => 'boolean',
            'sort_order'          => 'integer',
        ]);

        $data['slug'] = Str::slug($data['name']);

        return response()->json(Coach::create($data), 201);
    }

    public function update(Request $request, Coach $coach): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:150',
            'description'         => 'nullable|string',
            'speciality'          => 'nullable|string|max:255',
            'pinecone_namespace'  => 'nullable|string|max:100',
            'system_prompt_key'   => 'nullable|string|max:100',
            'is_launch_coach'     => 'boolean',
            'is_active'           => 'boolean',
            'sort_order'          => 'integer',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $coach->update($data);

        return response()->json($coach->fresh());
    }

    public function toggle(Coach $coach): JsonResponse
    {
        $coach->update(['is_active' => !$coach->is_active]);

        return response()->json($coach->fresh());
    }

    public function destroy(Coach $coach): JsonResponse
    {
        $coach->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
