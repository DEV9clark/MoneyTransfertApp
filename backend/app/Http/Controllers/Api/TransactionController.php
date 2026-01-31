<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Exception;
use App\Models\Transaction;

class TransactionController extends Controller
{
    protected $transactionService;
    protected $tariffService;

    public function __construct(TransactionService $transactionService, \App\Services\TariffService $tariffService)
    {
        $this->transactionService = $transactionService;
        $this->tariffService = $tariffService;
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'client_name'   => 'required|string',
            'client_phone'  => 'required|string',
            'amount'        => 'required|numeric|min:1',
            'type'          => 'required|in:send,withdraw',
            'destination'   => 'required_if:type,send|string|nullable',
            'recipient_name'=> 'required_if:type,send|string|nullable',
            'recipient_phone'=> 'required_if:type,send|string|nullable',
        ]);

        try {
            // Calculate Tariff
            $amount = (float) $request->amount;
            $type = $request->type;
            $fee = $this->tariffService->calculateFee($amount, $type);
            $total = $amount + $fee;

            $data = [
                'client_name' => $request->client_name,
                'client_phone' => $request->client_phone,
                'amount' => $amount,
                'fee_amount' => $fee,
                'total_amount' => $total,
                'type' => $type,
                'destination' => $request->destination,
                'recipient_name' => $request->recipient_name,
                'recipient_phone' => $request->recipient_phone,
            ];

            $transaction = $this->transactionService->initiateTransfer(
                $request->user(),
                $data
            );

            return response()->json([
                'message' => 'Transfer initiated',
                'data'    => $transaction,
                'code'    => $transaction->reference
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $transaction = $this->transactionService->completeTransfer(
                $request->user(),
                $request->code
            );

            return response()->json([
                'message' => 'Transfer completed',
                'data'    => $transaction
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function balance(Request $request)
    {
        $balance = $this->transactionService->getBalance($request->user());
        return response()->json(['balance' => $balance]);
    }

    public function history(Request $request)
    {
        $history = $this->transactionService->getHistory($request->user());
        return response()->json(['data' => $history]);
    }

    public function calculateFees(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'type'   => 'required|string'
        ]);

        $amount = (float) $request->amount;
        $fee = $this->tariffService->calculateFee($amount, $request->type);

        return response()->json([
            'amount' => $amount,
            'fee'    => $fee,
            'total'  => $amount + $fee
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $transaction = Transaction::where('reference', $request->code)
            ->where('status', 'pending')
            ->where('type', 'send') // Only send transactions can be withdrawn
            ->with('client') // Load sender info
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Invalid or already processed code.'], 404);
        }

        return response()->json([
            'data' => [
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'sender' => $transaction->client->name,
                'sender_phone' => $transaction->client->phone,
                'recipient_name' => $transaction->recipient_name,
                'recipient_phone' => $transaction->recipient_phone,
                'created_at' => $transaction->created_at->format('Y-m-d H:i')
            ]
        ]);
    }

    public function pendingCount()
    {
        // Count transactions that are pending and are of type 'send' (receivable)
        $count = Transaction::where('status', 'pending')
            ->where('type', 'send')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function stats(Request $request)
    {
        $filter = $request->get('filter', 'day'); // day, week, month, year
        $currency = $request->get('currency', 'USD'); // Default to USD if not provided
        
        $query = Transaction::query();
        
        // Apply Currency Filter
        $query->where('currency', $currency);

        // Apply Time Filter
        switch ($filter) {
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
            case 'day':
            default:
                $query->whereDate('created_at', now());
                break;
        }

        // Calculate Stats
        $volume = $query->sum('amount');
        $count = $query->count();
        $fees = 0;

        // Only calculate fees if user is admin
        if ($request->user()->role === 'admin') {
            // Calculate fees: sum of (total_amount - amount) for 'send' transactions
            // Alternatively, use a stored 'fee_amount' column if it exists.
            // Based on previous code, we calculate fee dynamically but don't seem to store it explicitly as 'fee_amount' in the database schema yet, 
            // wait, looking at the transfer method:
            // 'fee_amount' => $fee, 
            // 'total_amount' => $total,
            // These seem to be passed to initateTransfer. Let's assume they are stored in JSON or columns.
            // I'll check migration later, but for now let's sum 'fee' column if it exists or do the math.
            // Checking migration 2026_01_31_071506_add_details_to_transactions_table.php ...
            // It added 'fee_amount' and 'total_amount'.
            $fees = $query->sum('fee_amount'); 
        }

        return response()->json([
            'volume' => $volume,
            'count' => $count,
            'fees' => $fees,
            'filter' => $filter
        ]);
    }
}
