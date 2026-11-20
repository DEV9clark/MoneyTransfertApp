<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Gestion des agents par l'administrateur (création, activation, rechargement du float).
 */
class AgentController extends Controller
{
    public function index()
    {
        return response()->json(
            User::with('wallet')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:20|unique:users,phone_number',
            'country' => 'nullable|string|max:100',
            'role' => 'nullable|in:agent,admin',
            'currency' => 'nullable|string|size:3',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'] ?? null,
                'country' => $data['country'] ?? null,
                'role' => $data['role'] ?? 'agent',
                'is_active' => true,
            ]);

            Wallet::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'type' => Wallet::TYPE_AGENT,
                'currency' => strtoupper($data['currency'] ?? 'XOF'),
                'balance' => 0,
            ]);

            return $user;
        });

        AuditLogger::log('agent.created', $user);

        return response()->json($user->load('wallet'), 201);
    }

    public function updateStatus(Request $request, User $user)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 422);
        }

        $user->update(['is_active' => $data['is_active']]);
        if (!$data['is_active']) {
            $user->tokens()->delete();
        }

        AuditLogger::log($data['is_active'] ? 'agent.activated' : 'agent.deactivated', $user);

        return response()->json($user->load('wallet'));
    }

    public function topup(Request $request, User $user, TransactionService $service)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000000',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            $transaction = $service->topup($request->user(), $user, (float) $data['amount'], $data['note'] ?? null);
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatus());
        }

        return response()->json([
            'message' => 'Portefeuille rechargé.',
            'data' => $transaction,
            'balance' => $user->wallet()->first()->balance,
        ]);
    }
}
