<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PromptTemplate::with(['coach:id,name', 'language:id,name,code']);

        if ($request->filled('coach_id')) {
            $query->where('coach_id', $request->coach_id);
        }

        if ($request->filled('language_id')) {
            $query->where('language_id', $request->language_id);
        }

        if ($request->filled('template_type')) {
            $query->where('template_type', $request->template_type);
        }

        return response()->json($query->orderBy('coach_id')->orderBy('template_type')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coach_id'      => 'required|exists:coaches,id',
            'language_id'   => 'required|exists:languages,id',
            'template_type' => 'required|string|max:100',
            'title'         => 'required|string|max:200',
            'content'       => 'required|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'boolean',
            'version'       => 'integer|min:1',
        ]);

        return response()->json(PromptTemplate::create($data), 201);
    }

    public function update(Request $request, PromptTemplate $prompt): JsonResponse
    {
        $data = $request->validate([
            'coach_id'      => 'sometimes|exists:coaches,id',
            'language_id'   => 'sometimes|exists:languages,id',
            'template_type' => 'sometimes|string|max:100',
            'title'         => 'sometimes|string|max:200',
            'content'       => 'sometimes|string',
            'variables'     => 'nullable|array',
            'is_active'     => 'boolean',
            'version'       => 'integer|min:1',
        ]);

        $prompt->update($data);

        return response()->json($prompt->fresh()->load(['coach:id,name', 'language:id,name,code']));
    }

    public function toggle(PromptTemplate $prompt): JsonResponse
    {
        $prompt->update(['is_active' => !$prompt->is_active]);

        return response()->json($prompt->fresh());
    }

    public function destroy(PromptTemplate $prompt): JsonResponse
    {
        $prompt->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
