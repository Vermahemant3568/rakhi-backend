<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserPlan;

class PlanController extends Controller
{
    public function index()
    {
        $plans = UserPlan::where('user_id', auth()->id())
            ->with('coach')
            ->latest('generated_at')
            ->get();

        return response()->json(['success' => true, 'plans' => $plans]);
    }

    public function download(int $id)
    {
        $plan = UserPlan::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json(['success' => true, 'file_url' => $plan->file_url]);
    }
}
