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

        $clients = Client::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
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

    public function show(Client $client)
    {
        return response()->json($client->load('country', 'transactions'));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return response()->json(null, 204);
    }
}
