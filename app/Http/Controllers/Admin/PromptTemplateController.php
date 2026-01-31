<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\Request;

class PromptTemplateController extends Controller
{
    public function index()
    {
        if (request()->expectsJson()) {
            $templates = PromptTemplate::all();
            return response()->json($templates);
        }
        
        return view('admin.ai-control.prompt-templates');
    }

    public function store(Request $request)
    {
        $template = PromptTemplate::create($request->only(['type', 'template', 'is_active']));
        return response()->json($template);
    }

    public function update(Request $request, PromptTemplate $template)
    {
        $template->update($request->only(['template', 'is_active']));
        return response()->json($template);
    }

    public function toggle(PromptTemplate $template)
    {
        $template->update(['is_active' => !$template->is_active]);
        return response()->json($template);
    }
}
