<?php

namespace App\Http\Middleware;

use App\Models\ChatSession;
use Closure;
use Illuminate\Http\Request;

class EnsureSessionOwnership
{
    /**
     * Intercepts any request that carries a session_id (body or route) and
     * verifies the session belongs to the authenticated user before the
     * controller even runs. This is a defence-in-depth layer — controllers
     * still do their own scoped queries, but this stops bad requests early.
     */
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->route('sessionId')
            ?? $request->input('session_id');

        if ($sessionId) {
            $userId = auth()->id();

            $owned = ChatSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->exists();

            if (!$owned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        return $next($request);
    }
}
