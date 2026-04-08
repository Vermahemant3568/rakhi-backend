<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Language::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'required|string|max:10',
            'native_name' => 'nullable|string|max:100',
            'tts_code'    => 'nullable|string|max:20',
            'stt_code'    => 'nullable|string|max:20',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);
        return response()->json(Language::create($data), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $language = Language::findOrFail($id);
        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'code'        => 'sometimes|string|max:10',
            'native_name' => 'nullable|string|max:100',
            'tts_code'    => 'nullable|string|max:20',
            'stt_code'    => 'nullable|string|max:20',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);
        $language->update($data);
        return response()->json($language->fresh());
    }

    public function toggle($id): JsonResponse
    {
        $language = Language::findOrFail($id);
        $language->update(['is_active' => !$language->is_active]);
        return response()->json($language->fresh());
    }
}
