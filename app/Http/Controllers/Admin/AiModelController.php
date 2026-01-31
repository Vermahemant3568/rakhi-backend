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
            $models = AiModel::all();
            return response()->json($models);
        }
        
        return view('admin.ai-control.ai-models');
    }

    public function activate(AiModel $model)
    {
        // Deactivate all other models
        AiModel::where('id', '!=', $model->id)->update(['is_active' => false]);
        
        // Activate selected model
        $model->update(['is_active' => true]);
        
        return response()->json($model);
    }

    public function store(Request $request)
    {
        $model = AiModel::create($request->only(['provider', 'model_name']));
        return response()->json($model);
    }
}
