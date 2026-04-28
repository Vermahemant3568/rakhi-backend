<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            // Token expired — attempt silent refresh within the refresh window
            return $this->tryRefresh($request, $next);
        } catch (TokenInvalidException $e) {
            return $this->unauthorized('Invalid session. Please login again.');
        } catch (JWTException $e) {
            return $this->unauthorized('Session not found. Please login again.');
        }

        if (!$user || !($user instanceof \App\Models\User)) {
            return $this->unauthorized('Unauthorized');
        }

        if ($user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        auth()->setUser($user);
        $user->update(['last_active_at' => now()]);

        return $next($request);
    }

    /**
     * Attempt to refresh an expired token silently.
     * If the token is still within the refresh_ttl window, issue a new token
     * and continue the request — the new token is returned in the response header
     * so the client can store it without any interruption.
     */
    private function tryRefresh(Request $request, Closure $next)
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            $user     = JWTAuth::setToken($newToken)->toUser();

            if (!$user || !($user instanceof \App\Models\User)) {
                return $this->unauthorized('Session restore failed. Please login again.');
            }

            if ($user->is_banned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been suspended. Please contact support.',
                ], 403);
            }

            auth()->setUser($user);
            $user->update(['last_active_at' => now()]);

            // Continue the request and attach the refreshed token in the response
            // header so the client can silently update its stored token.
            $response = $next($request);
            $response->headers->set('X-Token-Refreshed', $newToken);
            $response->headers->set('X-Token-Expires-At', now()->addMinutes(config('jwt.ttl'))->toIso8601String());

            return $response;

        } catch (TokenExpiredException $e) {
            // Refresh window also expired — must re-authenticate
            return $this->unauthorized('Session expired. Please login again.', 'session_expired');
        } catch (JWTException $e) {
            return $this->unauthorized('Session invalid. Please login again.', 'session_invalid');
        }
    }

    private function unauthorized(string $message, string $code = 'unauthorized')
    {
        return response()->json([
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ], 401);
    }
}
