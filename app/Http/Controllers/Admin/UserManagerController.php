<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\MealLog;
use App\Services\AI\WelcomeConsultationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function allPlans(Request $request): JsonResponse
    {
        $query = \App\Models\UserPlan::with(['user:id,first_name,last_name,mobile', 'coach:id,name'])
            ->orderBy('id', 'desc');

        if ($request->filled('plan_type')) {
            $query->where('plan_type', $request->plan_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $plans = $query->paginate(50);

        // Ensure generated_at is always present (model has timestamps=false)
        $plans->getCollection()->transform(function ($plan) {
            $plan->generated_at = $plan->generated_at?->toDateTimeString() ?? null;
            return $plan;
        });

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    public function mealLogs($id): JsonResponse
    {
        return response()->json(
            MealLog::where('user_id', $id)->latest()->paginate(20)
        );
    }

    public function regeneratePlans($id): JsonResponse
    {
        $user = User::with(['goals', 'coaches'])->findOrFail($id);

        // Find the consultation session
        $session = ChatSession::where('user_id', $user->id)
            ->where('session_type', 'chat')
            ->where('is_first_consultation', 0) // completed
            ->orderBy('id', 'desc')
            ->first();

        if (!$session) {
            // Try any chat session
            $session = ChatSession::where('user_id', $user->id)
                ->where('session_type', 'chat')
                ->orderBy('id', 'desc')
                ->first();
        }

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'No chat session found for this user'], 404);
        }

        try {
            app(WelcomeConsultationService::class)->generateAllPlans($user, $session->id);
            return response()->json(['success' => true, 'message' => 'Plans generated successfully for user ' . $user->first_name]);
        } catch (\Throwable $e) {
            Log::error('Admin regeneratePlans failed for user ' . $id . ': ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
