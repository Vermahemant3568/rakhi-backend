<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AdminAuditLog as AdminAuditLogModel;

class AdminAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (session('admin_id') && $request->method() !== 'GET') {
            AdminAuditLogModel::create([
                'admin_id' => session('admin_id'),
                'action' => $request->method() . ' ' . $request->path(),
                'resource' => $request->route()?->getName(),
                'details' => $request->except(['password', '_token']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }

        return $response;
    }
}