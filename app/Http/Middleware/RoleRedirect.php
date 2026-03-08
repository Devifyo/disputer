<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $role  The required role (e.g., 'admin' or 'user')
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        // 1. Ensure user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // 2. Safety Check: If no role was passed (e.g., just 'role_access' instead of 'role_access:admin')
        // we skip the check to prevent the "Too few arguments" crash.
        if (!$role) {
            return $next($request);
        }

        $userRole = auth()->user()->getRoleNames()->first();
        // 3. Cross-Redirect Logic
        // If the user's role does not match the route requirement
        if ($userRole !== $role) {
            
            // Scenario: Admin tries to access a User route
            if ($userRole === config('roles.admin.name')) {
                return redirect()->route('admin.dashboard');
            }

            // Scenario: Standard User tries to access an Admin route
            if ($userRole === config('roles.user.name')) {
                return redirect()->route('user.dashboard');
            }
            
            // Fallback for unauthorized roles
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}