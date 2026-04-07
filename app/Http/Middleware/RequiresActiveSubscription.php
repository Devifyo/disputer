<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || !$user->canCreateCase()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'An active subscription is required to access this feature.'], 403);
            }

            return redirect()->route('user.dashboard')
                ->with('error', 'You need an active subscription or available cases to access Email Templates.');
        }

        return $next($request);
    }
}
