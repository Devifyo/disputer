<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The route half of a module toggle: when an admin switches a module off in
 * Settings, its pages and APIs refuse - on both portals - instead of merely
 * vanishing from the nav.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (Modules::enabled($module)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(404);
        }

        $home = $request->is('admin/*') ? route('admin.dashboard') : route('user.dashboard');

        return redirect($home)->with('info', 'That module is currently switched off in Settings.');
    }
}
