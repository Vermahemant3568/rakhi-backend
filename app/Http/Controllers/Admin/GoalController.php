<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Coach;
use App\Services\Coach\CoachRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoalController extends Controller
{
    // Goals that cannot be deleted — core to onboarding flow
    private const PROTECTED_SLUGS = [
        'manage-diabetes',
        'lose-weight',
        'eat-healthier',
        'build-healthy-habits',
    ];

    public function __construct(private CoachRouter $coachRouter) {}

    // ─────────────────────────────────────────────
    // LIST ALL GOALS
    // ─────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $goals = Goal::orderBy('sort_order')
            ->withCount(['users'])
            ->with('coach:id,name,slug,is_active')
            ->get()
            ->map(fn($g) => array_merge($g->toArray(), [
                'is_protected'       => in_array($g->slug, self::PROTECTED_SLUGS),
                'mapped_coach_slug'  => $this->getCoachSlugForGoal($g->slug),
            ]));

        return response()->json([
            'success' => true,
            'goals'   => $goals,
            'total'   => $goals->count(),
            'stats'   => [
                'total'    => $goals->count(),
                'active'   => $goals->where('is_active', true)->count(),
                'inactive' => $goals->where('is_active', false)->count(),
                'total_user_assignments' => $goals->sum('users_count'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // SHOW SINGLE GOAL
    // ─────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $goal = Goal::withCount('users')
            ->with('coach:id,name,slug')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'goal'    => array_merge($goal->toArray(), [
                'is_protected'      => in_array($goal->slug, self::PROTECTED_SLUGS),
                'mapped_coach_slug' => $this->getCoachSlugForGoal($goal->slug),
            ]),
        ]);
    }

    // ─────────────────────────────────────────────
    // CREATE GOAL
    // ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'icon'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'coach_id'    => 'nullable|exists:coaches,id',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);

        $slug = Str::slug($data['name']);

        if (Goal::where('slug', $slug)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "A goal with this name already exists (slug: {$slug})",
            ], 422);
        }

        $data['slug']       = $slug;
        $data['is_active']  = $data['is_active']  ?? true;
        $data['sort_order'] = $data['sort_order']  ?? (Goal::max('sort_order') + 1);

        $goal = Goal::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Goal created successfully',
            'goal'    => $goal->load('coach:id,name,slug'),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // UPDATE GOAL
    // ─────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $goal = Goal::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'icon'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'coach_id'    => 'nullable|exists:coaches,id',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);

        // Slug uniqueness check on rename
        if (isset($data['name'])) {
            $newSlug = Str::slug($data['name']);
            if ($newSlug !== $goal->slug && Goal::where('slug', $newSlug)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "Another goal already has this name (slug: {$newSlug})",
                ], 422);
            }
            $data['slug'] = $newSlug;
        }

        // Prevent deactivating protected goals
        if (isset($data['is_active']) && !$data['is_active'] && in_array($goal->slug, self::PROTECTED_SLUGS)) {
            return response()->json([
                'success' => false,
                'message' => "'{$goal->name}' is a core goal and cannot be deactivated.",
            ], 422);
        }

        $goal->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Goal updated successfully',
            'goal'    => $goal->fresh()->load('coach:id,name,slug'),
        ]);
    }

    // ─────────────────────────────────────────────
    // TOGGLE is_active
    // ─────────────────────────────────────────────

    public function toggle(int $id): JsonResponse
    {
        $goal = Goal::findOrFail($id);

        if ($goal->is_active && in_array($goal->slug, self::PROTECTED_SLUGS)) {
            return response()->json([
                'success' => false,
                'message' => "'{$goal->name}' is a core goal and cannot be deactivated.",
            ], 422);
        }

        $goal->update(['is_active' => !$goal->is_active]);

        return response()->json([
            'success' => true,
            'message' => $goal->is_active ? 'Goal activated' : 'Goal deactivated',
            'goal'    => $goal->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────
    // REORDER GOALS
    // ─────────────────────────────────────────────

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order'              => 'required|array',
            'order.*.id'         => 'required|integer|exists:goals,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->order as $item) {
            Goal::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Goal order updated',
        ]);
    }

    // ─────────────────────────────────────────────
    // DELETE GOAL
    // ─────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $goal = Goal::withCount('users')->findOrFail($id);

        if (in_array($goal->slug, self::PROTECTED_SLUGS)) {
            return response()->json([
                'success' => false,
                'message' => "'{$goal->name}' is a core goal and cannot be deleted.",
            ], 422);
        }

        if ($goal->users_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete '{$goal->name}' — {$goal->users_count} users have this goal. Deactivate it instead.",
            ], 422);
        }

        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => "'{$goal->name}' deleted successfully",
        ]);
    }

    // ─────────────────────────────────────────────
    // HELPER: get the coach slug mapped to a goal slug
    // ─────────────────────────────────────────────

    private function getCoachSlugForGoal(string $goalSlug): ?string
    {
        $map = [
            'manage-diabetes'       => 'diabetes-coach',
            'lose-weight'           => 'weight-loss-coach',
            'manage-pcos'           => 'pcos-thyroid-coach',
            'thyroid-management'    => 'pcos-thyroid-coach',
            'build-muscle'          => 'fitness-coach',
            'eat-healthier'         => 'diet-nutrition-coach',
            'improve-mental-health' => 'mental-wellness-coach',
            'reduce-stress'         => 'stress-coach',
            'improve-sleep'         => 'sleep-coach',
            'boost-energy'          => 'energy-coach',
            'pregnancy-wellness'    => 'pregnancy-coach',
            'postpartum-recovery'   => 'postpartum-coach',
            'build-healthy-habits'  => 'habit-coach',
        ];

        return $map[$goalSlug] ?? null;
    }
}
