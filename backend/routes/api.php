<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use MoneyTransfer\InlineEdit\Http\Controllers\InlineEditController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Route générique pour tous les modèles
    Route::patch('/{model}/{id}', [InlineEditController::class, 'update']);
    
    // Mise à jour en masse (optionnel)
    Route::post('/bulk-update', [InlineEditController::class, 'bulkUpdate']);
});


Route::get('/debug-data', function() {
    $transactions = \App\Models\Transaction::latest()->take(5)->get();
    $todayVolume = \App\Models\Transaction::whereDate('created_at', now())->sum('amount');
    $todayFee = \App\Models\Transaction::whereDate('created_at', now())->sum('fee_amount');

    return response()->json([
        'server_time' => now()->toDateTimeString(),
        'today_volume' => $todayVolume,
        'today_fee' => $todayFee,
        'last_5_transactions' => $transactions
    ]);
});

Route::get('/fix-fees', function() {
    $transactions = \App\Models\Transaction::where('fee_amount', 0)->get();
    $service = new \App\Services\TariffService();
    $updated = 0;

    foreach ($transactions as $t) {
        $fee = $service->calculateFee($t->amount, $t->type);
        if ($fee > 0) {
            $t->fee_amount = $fee;
            $t->total_amount = $t->amount + $fee;
            $t->save();
            $updated++;
        }
    }

    return response()->json(['message' => "Fixed $updated transactions"]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/transfer', [App\Http\Controllers\Api\TransactionController::class, 'transfer']);
    Route::post('/withdraw', [App\Http\Controllers\Api\TransactionController::class, 'withdraw']);
    Route::get('/balance', [App\Http\Controllers\Api\TransactionController::class, 'balance']);
    Route::get('/transactions', [App\Http\Controllers\Api\TransactionController::class, 'history']);
    
    Route::get('/transactions/stats', [App\Http\Controllers\Api\TransactionController::class, 'stats']);
    Route::get('/transactions/pending-count', [App\Http\Controllers\Api\TransactionController::class, 'pendingCount']);
    Route::post('/transactions/calculate-fees', [App\Http\Controllers\Api\TransactionController::class, 'calculateFees']);
    Route::post('/transactions/verify', [App\Http\Controllers\Api\TransactionController::class, 'verifyCode']);
});

// Routes accessibles via Sanctum (API mobile) ET session web (plateforme web)
Route::middleware('auth:sanctum,web')->group(function () {
    Route::get('/clients/search', [App\Http\Controllers\Api\ClientController::class, 'search']);
    Route::get('/clients', [App\Http\Controllers\Api\ClientController::class, 'index']);
    Route::post('/clients', [App\Http\Controllers\Api\ClientController::class, 'store']);
    Route::get('/clients/{client}', [App\Http\Controllers\Api\ClientController::class, 'show']);
    Route::put('/clients/{client}', [App\Http\Controllers\Api\ClientController::class, 'update']);
    Route::delete('/clients/{client}', [App\Http\Controllers\Api\ClientController::class, 'destroy']);
});
