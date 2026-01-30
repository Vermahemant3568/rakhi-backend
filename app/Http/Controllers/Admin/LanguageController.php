<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Language::orderBy('id', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:languages,code'
        ]);

        $language = Language::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Language added',
            'data' => $language
        ]);
    }

    public function update(Request $request, $id)
    {
        $language = Language::findOrFail($id);

        $language->update($request->only(['name', 'code', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'Language updated',
            'data' => $language
        ]);
    }

    public function destroy($id)
    {
        Language::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Language deleted'
        ]);
    }
    
    // Web methods
    public function webIndex()
    {
        $languages = Language::orderBy('id', 'desc')->get();
        return view('admin.languages.index', compact('languages'));
    }
    
    public function webStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:languages,code'
        ]);

        Language::create($request->all());
        return redirect()->route('admin.languages.index')->with('success', 'Language added successfully');
    }
    
    public function webUpdate(Request $request, $id)
    {
        $language = Language::findOrFail($id);
        $language->update($request->only(['name', 'code', 'is_active']));
        return redirect()->route('admin.languages.index')->with('success', 'Language updated successfully');
    }
    
    public function webDestroy($id)
    {
        Language::findOrFail($id)->delete();
        return redirect()->route('admin.languages.index')->with('success', 'Language deleted successfully');
    }
}
