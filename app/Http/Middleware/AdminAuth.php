<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use App\Models\Admin;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();

            if ($payload->get('role') !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $admin = Admin::findOrFail($payload->get('sub'));

            if (!$admin->is_active) {
                return response()->json(['message' => 'Account is inactive'], 403);
            }

            $request->setUserResolver(fn() => $admin);

        } catch (TokenExpiredException) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (TokenInvalidException) {
            return response()->json(['message' => 'Token invalid'], 401);
        } catch (JWTException) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        return $next($request);
    }
}
