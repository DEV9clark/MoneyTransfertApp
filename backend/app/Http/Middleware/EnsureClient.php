<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;

/**
 * Routes de l'application client : seul un jeton client est accepté,
 * et un compte client désactivé perd l'accès.
 */
class EnsureClient
{
    public function handle(Request $request, Closure $next)
    {
        $client = $request->user();

        if (!$client instanceof Client) {
            return response()->json(['message' => 'Accès réservé aux clients.'], 403);
        }

        if ($client->is_active === false) {
            $client->tokens()->delete();

            return response()->json(['message' => 'Compte suspendu. Contactez le service client.'], 403);
        }

        return $next($request);
    }
}
