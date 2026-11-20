<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Connexion de l'application mobile (jeton Sanctum). Limité par le rate limiter "login".
     * La création de comptes agents se fait désormais par un admin : POST /api/admin/agents.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            AuditLogger::log('auth.failed', null, ['email' => $request->email]);
            return response()->json(['message' => 'Email ou mot de passe incorrect.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Compte désactivé. Contactez votre administrateur.'], 403);
        }

        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;
        AuditLogger::log('auth.login', $user, ['channel' => 'api'], $user->id);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('wallet'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('wallet'));
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }
}
