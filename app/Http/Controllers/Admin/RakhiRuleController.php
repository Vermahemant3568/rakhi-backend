<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RakhiRule;
use Illuminate\Http\Request;

class RakhiRuleController extends Controller
{
    public function index()
    {
        if (request()->expectsJson()) {
            $rules = RakhiRule::all();
            return response()->json($rules);
        }
        
        return view('admin.ai-control.rakhi-rules');
    }

    public function update(Request $request, RakhiRule $rule)
    {
        $rule->update($request->only(['value', 'is_active']));
        return response()->json($rule);
    }

    public function toggle(RakhiRule $rule)
    {
        $rule->update(['is_active' => !$rule->is_active]);
        return response()->json($rule);
    }
}
