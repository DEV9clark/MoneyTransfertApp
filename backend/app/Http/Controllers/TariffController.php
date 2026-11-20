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
        $data = $this->validated($request);
        if ($error = $this->overlapError($data)) {
            return response()->json(['message' => $error], 422);
        }

        $tariff = Tariff::create($data);
        \App\Services\AuditLogger::log('tariff.created', $tariff, $data);
        return response()->json(['success' => true]);
    }

    public function update(Request $request, Tariff $tariff)
    {
        $this->authorizeAdmin($request);
        $data = $this->validated($request);
        if ($error = $this->overlapError($data, $tariff->id)) {
            return response()->json(['message' => $error], 422);
        }

        $before = $tariff->only(['type', 'min_amount', 'max_amount', 'fee_amount']);
        $tariff->update($data);
        \App\Services\AuditLogger::log('tariff.updated', $tariff, ['before' => $before, 'after' => $data]);
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Tariff $tariff)
    {
        $this->authorizeAdmin($request);
        \App\Services\AuditLogger::log('tariff.deleted', $tariff, $tariff->only(['type', 'min_amount', 'max_amount', 'fee_amount']));
        $tariff->delete();
        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gt:min_amount',
            'type' => 'required|in:send,withdraw',
            'fee_amount' => 'required|numeric|min:0',
        ]);
    }

    /** Deux tranches d'un même type ne doivent pas se chevaucher. */
    private function overlapError(array $data, ?int $ignoreId = null): ?string
    {
        $min = (float) $data['min_amount'];
        $max = isset($data['max_amount']) && $data['max_amount'] !== '' ? (float) $data['max_amount'] : null;

        $overlap = Tariff::where('type', $data['type'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($min) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $min);
            })
            ->when($max !== null, fn ($q) => $q->where('min_amount', '<=', $max))
            ->first();

        return $overlap
            ? 'Cette tranche chevauche une tranche existante (' . $overlap->min_amount . ' - ' . ($overlap->max_amount ?? '∞') . ').'
            : null;
    }

    private function authorizeAdmin(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
    }
}
