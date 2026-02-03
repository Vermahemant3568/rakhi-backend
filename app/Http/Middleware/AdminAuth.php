<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AdminUser;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('admin_id')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return redirect()->route('admin.login');
        }

        // Check if admin still exists and is active
        $admin = AdminUser::find(session('admin_id'));
        if (!$admin || !$admin->is_active || $admin->isLocked()) {
            session()->flush();
            return redirect()->route('admin.login')->withErrors(['email' => 'Account access denied']);
        }

        return $next($request);
    }
}