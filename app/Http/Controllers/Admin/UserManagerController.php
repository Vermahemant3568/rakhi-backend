<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MealLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('mobile', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('is_banned')) {
            $query->where('is_banned', $request->is_banned);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show($id): JsonResponse
    {
        $user = User::with(['goals', 'coaches'])->findOrFail($id);
        return response()->json($user);
    }

    public function ban(Request $request, $id): JsonResponse
    {
        $request->validate(['ban_reason' => 'nullable|string']);

        $user = User::findOrFail($id);
        $user->update([
            'is_banned'  => 1,
            'ban_reason' => $request->ban_reason,
        ]);

        return response()->json(['message' => 'User banned']);
    }

    public function unban($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update([
            'is_banned'  => 0,
            'ban_reason' => null,
        ]);

        return response()->json(['message' => 'User unbanned']);
    }

    public function chats($id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(
            $user->chatSessions()->with('coach:id,name')->orderBy('started_at','desc')->paginate(20)
        );
    }

    public function plans($id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(
            $user->userPlans()->with('coach:id,name')->orderBy('generated_at','desc')->paginate(20)
        );
    }

    public function mealLogs($id): JsonResponse
    {
        return response()->json(
            MealLog::where('user_id', $id)->latest()->paginate(20)
        );
    }
}
