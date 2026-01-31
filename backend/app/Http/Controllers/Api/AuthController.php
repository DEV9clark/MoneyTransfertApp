<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'password'    => 'required|string|min:8',
            'phone_number'=> 'nullable|string|max:20',
            'country'     => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) 
        {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'phone_number'=> $request->phone_number,
            'country'     => $request->country,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Create default wallet
        Wallet::create([
            'uuid'        => Str::uuid(),
            'user_id'     => $user->id,
            'currency'    => 'XOF', // Default for now
            'balance'     => 0,
        ]);

        return response()->json([
            'access_token'=> $token,
            'token_type'  => 'Bearer',
            'user'        => $user
        ]);
    }

    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) 
        {
            return response()->json([
                'message' => 'Invalid login details'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token'=> $token,
            'token_type'  => 'Bearer',
            'user'        => $user
        ]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
