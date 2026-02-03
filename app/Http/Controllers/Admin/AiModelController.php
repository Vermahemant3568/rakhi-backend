<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function index()
    {
        if (request()->expectsJson()) {
            $models = AiModel::all()->map(function ($model) {
                // Check if API key is configured for this provider
                $apiKeyConfigured = false;
                switch ($model->provider) {
                    case 'gemini':
                        $apiKeyConfigured = !empty(env('GEMINI_API_KEY'));
                        break;
                    case 'openai':
                        $apiKeyConfigured = !empty(env('OPENAI_API_KEY'));
                        break;
                    case 'claude':
                        $apiKeyConfigured = !empty(env('CLAUDE_API_KEY'));
                        break;
                }
                
                $model->api_key_configured = $apiKeyConfigured;
                return $model;
            });
            
            return response()->json($models);
        }
        
        return view('admin.ai-control.ai-models');
    }

    public function activate($id)
    {
        $model = AiModel::findOrFail($id);
        
        // Check if API key is configured
        $apiKeyConfigured = false;
        switch ($model->provider) {
            case 'gemini':
                $apiKeyConfigured = !empty(env('GEMINI_API_KEY'));
                break;
            case 'openai':
                $apiKeyConfigured = !empty(env('OPENAI_API_KEY'));
                break;
            case 'claude':
                $apiKeyConfigured = !empty(env('CLAUDE_API_KEY'));
                break;
        }
        
        if (!$apiKeyConfigured) {
            return response()->json([
                'success' => false,
                'message' => 'API key not configured for ' . $model->provider
            ], 400);
        }
        
        // Deactivate all other models
        AiModel::where('id', '!=', $id)->update(['is_active' => false]);
        
        // Activate selected model
        $model->update(['is_active' => true]);
        
        return response()->json($model);
    }

    public function store(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:gemini,openai,claude',
            'model_name' => 'required|string|max:255'
        ]);
        
        $model = AiModel::create($request->only(['provider', 'model_name']));
        return response()->json($model);
    }
    
    public function update(Request $request, $id)
    {
        $request->validate([
            'provider' => 'required|in:gemini,openai,claude',
            'model_name' => 'required|string|max:255'
        ]);
        
        $model = AiModel::findOrFail($id);
        $model->update($request->only(['provider', 'model_name']));
        return response()->json($model);
    }
    
    public function destroy($id)
    {
        $model = AiModel::findOrFail($id);
        
        if ($model->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active model'
            ], 400);
        }
        
        $model->delete();
        return response()->json(['success' => true]);
    }
}
