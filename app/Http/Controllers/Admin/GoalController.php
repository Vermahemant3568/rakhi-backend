<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoalController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Goal::orderBy('id', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|unique:goals,title',
        ]);

        $goal = Goal::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Goal created',
            'data' => $goal
        ]);
    }

    public function update(Request $request, $id)
    {
        $goal = Goal::findOrFail($id);

        $goal->update([
            'title' => $request->title ?? $goal->title,
            'slug' => Str::slug($request->title ?? $goal->title),
            'description' => $request->description,
            'is_active' => $request->is_active ?? $goal->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Goal updated',
            'data' => $goal
        ]);
    }

    public function destroy($id)
    {
        Goal::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Goal deleted'
        ]);
    }
    
    // Web methods
    public function webIndex()
    {
        $goals = Goal::orderBy('id', 'desc')->get();
        return view('admin.goals.index', compact('goals'));
    }
    
    public function webStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|unique:goals,title',
        ]);

        Goal::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description
        ]);
        
        return redirect()->route('admin.goals.index')->with('success', 'Goal added successfully');
    }
    
    public function webUpdate(Request $request, $id)
    {
        $goal = Goal::findOrFail($id);
        
        $goal->update([
            'title' => $request->title ?? $goal->title,
            'slug' => Str::slug($request->title ?? $goal->title),
            'description' => $request->description,
            'is_active' => $request->is_active ?? $goal->is_active
        ]);
        
        return redirect()->route('admin.goals.index')->with('success', 'Goal updated successfully');
    }
    
    public function webDestroy($id)
    {
        Goal::findOrFail($id)->delete();
        return redirect()->route('admin.goals.index')->with('success', 'Goal deleted successfully');
    }
}
