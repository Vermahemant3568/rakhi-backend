<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\UserCoach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoachController extends Controller
{
    // System coaches that cannot be deleted (core to Rakhi's routing)
    private const PROTECTED_SLUGS = [
        'diet-nutrition-coach',
        'habit-coach',
        'consultation-coach',
    ];

    // ─────────────────────────────────────────────
    // LIST ALL COACHES
    // ─────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $coaches = Coach::orderBy('sort_order')
            ->withCount('userCoaches')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'is_protected' => in_array($c->slug, self::PROTECTED_SLUGS),
            ]));

        return response()->json([
            'success' => true,
            'coaches' => $coaches,
            'total'   => $coaches->count(),
        ]);
    }

    // ─────────────────────────────────────────────
    // SHOW SINGLE COACH
    // ─────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $coach = Coach::withCount('userCoaches')->findOrFail($id);

        return response()->json([
            'success'      => true,
            'coach'        => array_merge($coach->toArray(), [
                'is_protected' => in_array($coach->slug, self::PROTECTED_SLUGS),
            ]),
        ]);
    }

    // ─────────────────────────────────────────────
    // CREATE COACH
    // ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'description'        => 'nullable|string',
            'speciality'         => 'nullable|string|max:255',
            'pinecone_namespace' => 'nullable|string|max:100',
            'system_prompt_key'  => 'nullable|string|max:100',
            'is_launch_coach'    => 'boolean',
            'is_active'          => 'boolean',
            'sort_order'         => 'integer',
        ]);

        $slug = Str::slug($data['name']);

        if (Coach::where('slug', $slug)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "A coach with this name already exists (slug: {$slug})",
            ], 422);
        }

        $data['slug']       = $slug;
        $data['is_active']  = $data['is_active']  ?? true;
        $data['sort_order'] = $data['sort_order']  ?? (Coach::max('sort_order') + 1);

        $coach = Coach::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Coach created successfully',
            'coach'   => $coach,
        ], 201);
    }

    // ─────────────────────────────────────────────
    // UPDATE COACH
    // ─────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $coach = Coach::findOrFail($id);

        $data = $request->validate([
            'name'               => 'sometimes|string|max:150',
            'description'        => 'nullable|string',
            'speciality'         => 'nullable|string|max:255',
            'pinecone_namespace' => 'nullable|string|max:100',
            'system_prompt_key'  => 'nullable|string|max:100',
            'is_launch_coach'    => 'boolean',
            'is_active'          => 'boolean',
            'sort_order'         => 'integer',
        ]);

        // Slug uniqueness check on rename
        if (isset($data['name'])) {
            $newSlug = Str::slug($data['name']);
            if ($newSlug !== $coach->slug && Coach::where('slug', $newSlug)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "Another coach already has this name (slug: {$newSlug})",
                ], 422);
            }
            $data['slug'] = $newSlug;
        }

        // Prevent deactivating protected coaches
        if (isset($data['is_active']) && !$data['is_active'] && in_array($coach->slug, self::PROTECTED_SLUGS)) {
            return response()->json([
                'success' => false,
                'message' => "'{$coach->name}' is a system coach and cannot be deactivated.",
            ], 422);
        }

        $coach->update($data);

        // If coach was deactivated, reassign affected users to diet-nutrition-coach
        if (isset($data['is_active']) && !$data['is_active']) {
            $this->reassignUsersFromCoach($coach->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coach updated successfully',
            'coach'   => $coach->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────
    // TOGGLE is_active
    // ─────────────────────────────────────────────

    public function toggle(int $id): JsonResponse
    {
        $coach = Coach::findOrFail($id);

        // Prevent deactivating protected coaches
        if ($coach->is_active && in_array($coach->slug, self::PROTECTED_SLUGS)) {
            return response()->json([
                'success' => false,
                'message' => "'{$coach->name}' is a system coach and cannot be deactivated.",
            ], 422);
        }

        $coach->update(['is_active' => !$coach->is_active]);

        // Reassign users if deactivated
        if (!$coach->is_active) {
            $this->reassignUsersFromCoach($coach->id);
        }

        return response()->json([
            'success' => true,
            'message' => $coach->is_active ? 'Coach activated' : 'Coach deactivated',
            'coach'   => $coach->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────
    // TOGGLE is_launch_coach
    // ─────────────────────────────────────────────

    public function toggleLaunch(int $id): JsonResponse
    {
        $coach = Coach::findOrFail($id);

        $coach->update(['is_launch_coach' => !$coach->is_launch_coach]);

        return response()->json([
            'success' => true,
            'message' => $coach->is_launch_coach
                ? "'{$coach->name}' added to launch coaches"
                : "'{$coach->name}' removed from launch coaches",
            'coach'   => $coach->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────
    // REORDER COACHES
    // ─────────────────────────────────────────────

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order'         => 'required|array',
            'order.*.id'    => 'required|integer|exists:coaches,id',
            'order.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->order as $item) {
            Coach::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coach order updated',
        ]);
    }

    // ─────────────────────────────────────────────
    // DELETE COACH
    // ─────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $coach = Coach::findOrFail($id);

        if (in_array($coach->slug, self::PROTECTED_SLUGS)) {
            return response()->json([
                'success' => false,
                'message' => "'{$coach->name}' is a system coach and cannot be deleted.",
            ], 422);
        }

        // Reassign users before deleting
        $this->reassignUsersFromCoach($coach->id);

        $coach->delete();

        return response()->json([
            'success' => true,
            'message' => "'{$coach->name}' deleted successfully",
        ]);
    }

    // ─────────────────────────────────────────────
    // HELPER: Reassign users when coach is deactivated/deleted
    // ─────────────────────────────────────────────

    private function reassignUsersFromCoach(int $coachId): void
    {
        $fallback = Coach::where('slug', 'diet-nutrition-coach')->where('is_active', 1)->first();
        if (!$fallback) return;

        // For users whose primary coach is being removed, assign fallback as primary
        UserCoach::where('coach_id', $coachId)
            ->where('is_primary', 1)
            ->update(['coach_id' => $fallback->id]);

        // Remove non-primary assignments to this coach
        UserCoach::where('coach_id', $coachId)
            ->where('is_primary', 0)
            ->delete();
    }
}
