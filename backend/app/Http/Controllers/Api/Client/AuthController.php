<?php

namespace App\Http\Controllers\Api\Client;

use App\Exceptions\TransferException;
use App\Http\Controllers\Controller;
use App\Services\ClientAuthService;
use Illuminate\Http\Request;

/**
 * Authentification de l'application client : téléphone + code PIN.
 */
class AuthController extends Controller
{
    protected ClientAuthService $auth;

    public function __construct(ClientAuthService $auth)
    {
        $this->auth = $auth;
    }

    /** Ce numéro a-t-il déjà un compte ? Oriente l'app vers « PIN » ou « inscription ». */
    public function check(Request $request)
    {
        $data = $this->phoneRules($request);

        return $this->handle(function () use ($data) {
            [, $national] = $this->auth->resolvePhone((int) $data['country_id'], $data['phone']);
            $client = $this->auth->findClient($national);

            return response()->json(['registered' => (bool) ($client && $client->hasAppAccess())]);
        });
    }

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'country_id' => 'required|integer',
            'phone' => 'required|string|max:20',
            'purpose' => 'required|in:register,reset_pin',
        ]);

        return $this->handle(function () use ($data) {
            [$country, $national] = $this->auth->resolvePhone((int) $data['country_id'], $data['phone']);
            $debugCode = $this->auth->sendOtp($country, $national, $data['purpose']);

            $response = [
                'message' => 'Un code de vérification a été envoyé par SMS.',
                'expires_in_minutes' => (int) config('transfer.client.otp_ttl_minutes', 10),
            ];
            if ($debugCode !== null) {
                $response['debug_code'] = $debugCode;
            }

            return response()->json($response);
        });
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'country_id' => 'required|integer',
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|max:10',
            'name' => 'required|string|min:2|max:255',
            'pin' => 'required|string|size:4|confirmed',
        ]);

        return $this->handle(function () use ($data) {
            [$country, $national] = $this->auth->resolvePhone((int) $data['country_id'], $data['phone']);
            $client = $this->auth->register($country, $national, $data['otp'], $data['name'], $data['pin']);

            return $this->tokenResponse($client, 201);
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'country_id' => 'required|integer',
            'phone' => 'required|string|max:20',
            'pin' => 'required|string|size:4',
        ]);

        return $this->handle(function () use ($data) {
            [, $national] = $this->auth->resolvePhone((int) $data['country_id'], $data['phone']);
            $client = $this->auth->login($national, $data['pin']);

            return $this->tokenResponse($client);
        });
    }

    public function resetPin(Request $request)
    {
        $data = $request->validate([
            'country_id' => 'required|integer',
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|max:10',
            'pin' => 'required|string|size:4|confirmed',
        ]);

        return $this->handle(function () use ($data) {
            [, $national] = $this->auth->resolvePhone((int) $data['country_id'], $data['phone']);
            $client = $this->auth->resetPin($national, $data['otp'], $data['pin']);

            return $this->tokenResponse($client);
        });
    }

    public function me(Request $request)
    {
        return response()->json($this->profile($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:255',
            'email' => 'sometimes|nullable|email|max:255',
        ]);
        $request->user()->update($data);

        return response()->json($this->profile($request->user()->fresh()));
    }

    public function changePin(Request $request)
    {
        $data = $request->validate([
            'current_pin' => 'required|string|size:4',
            'pin' => 'required|string|size:4|confirmed',
        ]);

        return $this->handle(function () use ($request, $data) {
            $this->auth->changePin($request->user(), $data['current_pin'], $data['pin']);

            return response()->json(['message' => 'Code PIN modifié.']);
        });
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    // ---------------------------------------------------------------------

    private function phoneRules(Request $request): array
    {
        return $request->validate([
            'country_id' => 'required|integer',
            'phone' => 'required|string|max:20',
        ]);
    }

    private function tokenResponse($client, int $status = 200)
    {
        return response()->json([
            'access_token' => $client->createToken('client-app', ['client'])->plainTextToken,
            'token_type' => 'Bearer',
            'client' => $this->profile($client),
        ], $status);
    }

    private function profile($client): array
    {
        $client->loadMissing('country');

        return [
            'uuid' => $client->uuid,
            'name' => $client->name,
            'phone' => $client->phone,
            'email' => $client->email,
            'country_id' => $client->country_id,
            'country' => optional($client->country)->name,
            'phone_code' => optional($client->country)->phone_code,
            'has_id_document' => $client->id_number !== null,
        ];
    }

    private function handle(callable $action)
    {
        try {
            return $action();
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatus());
        }
    }
}
