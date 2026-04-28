<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateConsultationReport;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use App\Models\UserPlan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * List latest version of each plan type for the plan screen.
     */
    public function index()
    {
        $userId = auth()->id();

        $plans = UserPlan::where('user_id', $userId)
            ->with('coach')
            ->latest('generated_at')
            ->get()
            ->groupBy('plan_type')
            ->map(fn($group) => $group->sortByDesc('version')->first())
            ->values();

        return response()->json(['success' => true, 'plans' => $plans]);
    }

    /**
     * Get full plan data for in-app display.
     * Secured: only the owning user can access.
     */
    public function show(int $id)
    {
        $plan = UserPlan::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'plan'    => [
                'id'           => $plan->id,
                'plan_type'    => $plan->plan_type,
                'version'      => $plan->version,
                'language'     => $plan->language,
                'generated_at' => $plan->generated_at,
                'file_url'     => $plan->file_url,
                'plan_data'    => $plan->plan_data,
                'coach'        => $plan->coach,
            ],
        ]);
    }

    /**
     * Download link for a specific plan.
     * Secured: only the owning user can access.
     */
    public function download(int $id)
    {
        $plan = UserPlan::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json(['success' => true, 'file_url' => $plan->file_url]);
    }

    /**
     * Get all versions of a specific plan type (version history).
     */
    public function history(string $planType)
    {
        $plans = UserPlan::where('user_id', auth()->id())
            ->where('plan_type', $planType)
            ->orderByDesc('version')
            ->get(['id', 'plan_type', 'version', 'language', 'generated_at', 'file_url']);

        return response()->json(['success' => true, 'plans' => $plans]);
    }

    /**
     * Get current plan generation state.
     * Frontend polls this to show real-time status (generating / completed / failed).
     */
    public function status()
    {
        $user  = auth()->user();
        $state = $user->plan_generation_state ?? 'not_started';

        $plans = UserPlan::where('user_id', $user->id)
            ->orderByDesc('generated_at')
            ->get()
            ->groupBy('plan_type')
            ->map(fn($group) => $group->sortByDesc('version')->first())
            ->map(fn($plan) => [
                'id'           => $plan->id,
                'plan_type'    => $plan->plan_type,
                'version'      => $plan->version,
                'file_url'     => $plan->file_url,
                'generated_at' => $plan->generated_at,
            ])
            ->values();

        return response()->json([
            'success'               => true,
            'plan_generation_state' => $state,
            'plans_ready'           => $plans->isNotEmpty(),
            'plans'                 => $plans,
        ]);
    }

    /**
     * Regenerate a specific plan type (or all three).
     * Guards against duplicate generation if already in progress.
     */
    public function regenerate(Request $request)
    {
        $request->validate([
            'plan_type'  => 'required|in:diet,fitness,consultation,all',
            'session_id' => 'nullable|exists:chat_sessions,id',
        ]);

        $user = auth()->user();
        $user->refresh();

        // Duplicate guard — do not restart if already generating
        if ($user->isPlanGenerating()) {
            $isHindi = str_starts_with($user->language?->code ?? 'en', 'hi');
            return response()->json([
                'success'               => false,
                'message'               => $isHindi
                    ? 'Aapka plan abhi ban raha hai — thoda wait karein.'
                    : 'Your plan is already being generated — please wait a moment.',
                'plan_generation_state' => 'generating',
            ], 409);
        }

        $type = $request->plan_type;

        // Resolve session — use provided or fall back to latest chat session
        $sessionId = $request->session_id
            ?? \App\Models\ChatSession::where('user_id', $user->id)
                ->where('session_type', 'chat')
                ->latest('id')
                ->value('id');

        if (!$sessionId) {
            return response()->json(['success' => false, 'message' => 'No active session found.'], 422);
        }

        // Security: verify session belongs to this user
        $session = \App\Models\ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $lang = $session->detected_language ?? 'en';

        // Set state BEFORE dispatching to prevent race condition
        $user->setPlanState('generating');

        match($type) {
            'consultation' => dispatch(new GenerateConsultationReport($user, $sessionId, $lang)),
            'diet'         => dispatch(new GenerateDietPlan($user, $sessionId, $lang)),
            'fitness'      => dispatch(new GenerateFitnessPlan($user, $sessionId, $lang)),
            'all'          => dispatch(new GenerateConsultationReport($user, $sessionId, $lang)),
            // 'all' chains: Report → Diet → Fitness automatically
        };

        $labels = [
            'diet'         => 'Diet Plan',
            'fitness'      => 'Fitness Plan',
            'consultation' => 'Consultation Report',
            'all'          => 'all three plans',
        ];

        $isHindi = str_starts_with($lang, 'hi');
        return response()->json([
            'success'               => true,
            'message'               => $isHindi
                ? "Aapka {$labels[$type]} dobara ban raha hai. Thodi der mein ready ho jaayega."
                : "Regenerating your {$labels[$type]}. You'll receive it shortly.",
            'plan_generation_state' => 'generating',
        ]);
    }
}
