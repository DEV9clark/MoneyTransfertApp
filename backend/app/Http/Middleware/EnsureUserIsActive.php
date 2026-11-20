<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Un agent désactivé par l'admin perd immédiatement l'accès (jetons API révoqués).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Un jeton client ne donne jamais accès aux routes agents.
        if ($user && !$user instanceof \App\Models\User) {
            return response()->json(['message' => 'Accès réservé aux agents.'], 403);
        }

        if ($user && $user->is_active === false) {
            if ($request->bearerToken()) {
                $user->tokens()->delete();
            } else {
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                }
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Compte désactivé. Contactez votre administrateur.'], 403)
                : redirect('/login');
        }

        return $next($request);
    }
}
