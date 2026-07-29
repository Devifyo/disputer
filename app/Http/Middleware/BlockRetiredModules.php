<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the retired case-management pages (Documents, My Cases) to
 * customers. The routes and controllers stay in the codebase - only the way
 * in is shut - so the module can be brought back by removing this middleware
 * from the route group.
 *
 * Hiding the nav link is not enough: anyone who kept a bookmark, or types
 * the URL, would still get in.
 */
class BlockRetiredModules
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep it honest for machines, friendly for people.
        if ($request->expectsJson()) {
            abort(404);
        }

        return redirect()
            ->route('user.dashboard')
            ->with('info', 'That section has moved - everything now lives under Flight Disputes.');
    }
}
