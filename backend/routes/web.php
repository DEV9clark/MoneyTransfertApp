<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\PreorderController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Back-office web (session + CSRF)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('login');
});
Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/clients', function () {
        return view('clients.index');
    })->name('clients.index');

    Route::get('/transactions', function () {
        return view('transactions.create');
    })->name('transactions.create');

    // Reçus PDF (ouverts dans un nouvel onglet pour impression)
    Route::get('/transactions/{transaction:uuid}/receipt/{kind}', [ReceiptController::class, 'show'])
        ->where('kind', 'deposit|withdrawal')
        ->name('transactions.receipt');

    // Endpoints JSON utilisés par les pages Blade (session + CSRF).
    // Préfixe distinct de /api : sinon ces routes écraseraient celles de l'API mobile (jeton Bearer).
    Route::prefix('bo-api')->group(function () {
        Route::get('countries', [CountryController::class, 'index']);
        Route::get('clients/search', [ClientController::class, 'search']);
        Route::apiResource('clients', ClientController::class);

        Route::get('transactions', [TransactionController::class, 'history']);
        Route::get('transactions/stats', [TransactionController::class, 'stats']);
        Route::get('transactions/pending-count', [TransactionController::class, 'pendingCount']);
        Route::post('transactions/calculate-fees', [TransactionController::class, 'calculateFees']);
        Route::post('transactions', [TransactionController::class, 'transfer']);

        Route::middleware('throttle:withdrawal')->group(function () {
            Route::post('transactions/verify', [TransactionController::class, 'verifyCode']);
            Route::post('transactions/withdraw', [TransactionController::class, 'withdraw']);
        });

        Route::post('preorders/lookup', [PreorderController::class, 'lookup'])->middleware('throttle:30,1');
        Route::post('preorders/confirm', [PreorderController::class, 'confirm']);

        Route::post('transactions/{transaction:uuid}/cancel', [TransactionController::class, 'cancel']);
        Route::post('transactions/{transaction:uuid}/resend-code', [TransactionController::class, 'resendCode'])
            ->middleware('throttle:3,60');

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('agents', [AgentController::class, 'index']);
            Route::post('agents', [AgentController::class, 'store']);
            Route::patch('agents/{user}/status', [AgentController::class, 'updateStatus']);
            Route::post('agents/{user}/topup', [AgentController::class, 'topup']);
        });
    });

    // Tarifs : consultation pour tous, modification réservée aux admins
    Route::get('tariffs', [TariffController::class, 'index'])->name('tariffs.index');
    Route::middleware('admin')->group(function () {
        Route::post('tariffs', [TariffController::class, 'store'])->name('tariffs.store');
        Route::put('tariffs/{tariff}', [TariffController::class, 'update'])->name('tariffs.update');
        Route::delete('tariffs/{tariff}', [TariffController::class, 'destroy'])->name('tariffs.destroy');
    });
});
