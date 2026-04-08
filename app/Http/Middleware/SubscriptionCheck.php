<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SubscriptionCheck
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user->isSubscribed()) {
            return response()->json([
                'success'  => false,
                'message'  => 'Subscription required',
                'redirect' => 'subscription_screen',
            ], 402);
        }

        return $next($request);
    }
}
