<?php

namespace App\Http\Controllers;

use App\Models\Tariff;
use Illuminate\Http\Request;

class TariffController extends Controller
{
    public function index()
    {
        $tariffs = Tariff::orderBy('min_amount')->get();
        return view('tariffs.index', compact('tariffs'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'min_amount' => 'required|numeric',
            'max_amount' => 'nullable|numeric|gt:min_amount',
            'type' => 'required|in:send,withdraw',
            'fee_amount' => 'required|numeric',
        ]);

        Tariff::create($data);
        return response()->json(['success' => true]);
    }

    public function update(Request $request, Tariff $tariff)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'min_amount' => 'required|numeric',
            'max_amount' => 'nullable|numeric|gt:min_amount',
            'type' => 'required|in:send,withdraw',
            'fee_amount' => 'required|numeric',
        ]);

        $tariff->update($data);
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Tariff $tariff)
    {
        $this->authorizeAdmin($request);
        $tariff->delete();
        return response()->json(['success' => true]);
    }

    private function authorizeAdmin(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
    }
}
