<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
            }
            abort(403, 'Action réservée aux administrateurs.');
        }

        return $next($request);
    }
}
