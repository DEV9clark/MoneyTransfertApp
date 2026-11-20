<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::with('country')->withCount('transactions')->latest()->get();
        return response()->json($clients);
    }

    public function search(Request $request)
    {
        $query = $request->get('query');
        
        if (!$query) {
            return response()->json([]);
        }

        $like = '%' . addcslashes($query, '%_\\') . '%';
        $clients = Client::where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('phone', 'like', $like);
            })
            ->with('country')
            ->withCount('transactions')
            ->limit(10)
            ->get();

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        $data['uuid'] = Str::uuid();

        $client = Client::create($data);

        return response()->json($client, 201);
    }

    public function show(Request $request, Client $client)
    {
        $client->load('country');
        $client->setRelation('transactions', $client->transactions()->visibleTo($request->user())->limit(50)->get());

        return response()->json($client);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return response()->json($client);
    }

    public function destroy(Request $request, Client $client)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }
        if ($client->transactions()->exists()) {
            return response()->json(['message' => 'Ce client a des transactions : suppression impossible (traçabilité).'], 409);
        }

        $client->delete();
        \App\Services\AuditLogger::log('client.deleted', $client);

        return response()->json(null, 204);
    }
}
