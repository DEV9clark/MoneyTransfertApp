<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Exception;
use App\Models\Transaction;
use App\Services\NotificationService;

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

            if($transaction)
            {
                if ($request->type === 'send') 
                {
                    // On crée le service de notification
                    $notificationService = new NotificationService();
                    
                    // On prépare les données
                    $data = [
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                        'reference' => $transaction->reference,
                        'fee' => $transaction->fee ?? 0,
                        'sender_name' => $transaction->client->name,
                        'sender_phone' => $transaction->client->phone,
                        'recipient_name' => $transaction->recipient_name,
                        'recipient_phone' => $transaction->recipient_phone,
                    ];
                    
                    // On envoie les notifications
                    $notificationService->notifySender($data);
                    $notificationService->notifyRecipient($data);
                }
            }

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

        try 
        {
            $transaction = $this->transactionService->completeTransfer(
                $request->user(),
                $request->code
            );

            if ($request->type === 'send') 
            {
                $notificationService = new NotificationService();
                $data = [
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'reference' => $transaction->reference,
                    'fee' => $transaction->fee ?? 0,
                    'sender_name' => $transaction->client->name,
                    'sender_phone' => $transaction->client->phone,
                    'recipient_name' => $transaction->recipient_name,
                    'recipient_phone' => $transaction->recipient_phone,
                ];
                
                // On envoie les notifications
                $notificationService->notifySender($data);
                $notificationService->notifyRecipient($data);
            }

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

        $notificationService = new NotificationService();

        $transaction = Transaction::where('reference', $request->code)
            ->where('status', 'pending')
            ->where('type', 'send') // Only send transactions can be withdrawn
            ->with('client') // Load sender info
            ->first();

        if (!$transaction) 
        {
            return response()->json(['message' => 'Invalid or already processed code.'], 404);
        }

        $data = [
            'reference' => $transaction->reference,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'sender' => $transaction->client->name,
            'sender_phone' => $transaction->client->phone,
            'recipient_name' => $transaction->recipient_name,
            'recipient_phone' => $transaction->recipient_phone,
            'created_at' => $transaction->created_at->format('Y-m-d H:i')
        ];

        $notificationService->notifyWithdrawal($data);

        return response()->json([
            'data' => $data
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

    public function pendingList()
    {
        // Get list of pending transactions (type=send, status=pending)
        $transactions = Transaction::where('status', 'pending')
            ->where('type', 'send')
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $transactions->map(function ($t) {
                return [
                    'reference' => $t->reference,
                    'amount' => $t->amount,
                    'currency' => $t->currency,
                    'sender' => $t->client->name ?? 'Unknown',
                    'sender_phone' => $t->client->phone ?? '',
                    'recipient_name' => $t->recipient_name,
                    'recipient_phone' => $t->recipient_phone,
                    'created_at' => $t->created_at->format('Y-m-d H:i'),
                ];
            })
        ]);
    }

    public function stats(Request $request)
    {
        $filter = $request->get('filter', 'day'); // day, week, month, year
        $currency = $request->get('currency', 'USD'); // Default to USD if not provided
        
        $query = Transaction::query();
        
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

        // Clone query for fees
        $feesQuery = clone $query;

        // Group by currency and sum volume
        $volumes = $query->select('currency', \Illuminate\Support\Facades\DB::raw('SUM(amount) as volume'))
            ->groupBy('currency')
            ->get();
            
        // Group by currency and sum fees (Admin only)
        $fees = [];
        if ($request->user()->role === 'admin') {
            $fees = $feesQuery->select('currency', \Illuminate\Support\Facades\DB::raw('SUM(fee_amount) as fees'))
                ->groupBy('currency')
                ->get();
        }

        return response()->json([
            'volumes' => $volumes, // [{currency: 'USD', volume: 100}, ...]
            'fees' => $fees,       // [{currency: 'USD', fees: 10}, ...]
            'filter' => $filter
        ]);
    }
}
