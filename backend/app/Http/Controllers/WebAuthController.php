<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            \App\Services\AuditLogger::log('auth.login', Auth::user(), ['channel' => 'web']);

            return response()->json(['message' => 'Login successful', 'redirect' => route('dashboard')]);
        }

        \App\Services\AuditLogger::log('auth.failed', null, ['email' => $request->email, 'channel' => 'web']);

        return response()->json([
            'message' => 'Identifiants incorrects ou compte désactivé.',
        ], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
