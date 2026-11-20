<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Api\Client\TransferController as ClientTransferController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\PreorderController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API (application mobile) — authentification par jeton Sanctum
|--------------------------------------------------------------------------
| Les anciennes routes publiques /debug-data, /fix-fees et /register ont été supprimées :
| elles exposaient les codes de retrait et permettaient de créer des agents sans contrôle.
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/balance', [TransactionController::class, 'balance']);
    Route::get('/countries', [CountryController::class, 'index']);

    // Envoi
    Route::post('/transfer', [TransactionController::class, 'transfer']);
    Route::post('/transactions/calculate-fees', [TransactionController::class, 'calculateFees']);

    // Retrait (limité contre les tentatives de devinette du code)
    Route::middleware('throttle:withdrawal')->group(function () {
        Route::post('/transactions/verify', [TransactionController::class, 'verifyCode']);
        Route::post('/withdraw', [TransactionController::class, 'withdraw']);
    });

    // Consultation
    Route::get('/transactions', [TransactionController::class, 'history']);
    Route::get('/transactions/stats', [TransactionController::class, 'stats']);
    Route::get('/transactions/pending-count', [TransactionController::class, 'pendingCount']);
    Route::get('/transactions/{transaction:uuid}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction:uuid}/cancel', [TransactionController::class, 'cancel']);
    Route::post('/transactions/{transaction:uuid}/resend-code', [TransactionController::class, 'resendCode'])
        ->middleware('throttle:3,60');

    // Reçus PDF : deposit (envoi, remis à l'expéditeur) / withdrawal (retrait, signé par le bénéficiaire)
    Route::get('/transactions/{transaction:uuid}/receipt/{kind}', [ReceiptController::class, 'show'])
        ->where('kind', 'deposit|withdrawal');

    // Pré-envois créés par les clients dans l'application : encaissement au guichet
    Route::post('/preorders/lookup', [PreorderController::class, 'lookup'])->middleware('throttle:30,1');
    Route::post('/preorders/confirm', [PreorderController::class, 'confirm']);

    // Clients
    Route::get('/clients/search', [ClientController::class, 'search']);
    Route::apiResource('/clients', ClientController::class);

    // Administration
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/agents', [AgentController::class, 'index']);
        Route::post('/agents', [AgentController::class, 'store']);
        Route::patch('/agents/{user}/status', [AgentController::class, 'updateStatus']);
        Route::post('/agents/{user}/topup', [AgentController::class, 'topup']);
    });
});

/*
|--------------------------------------------------------------------------
| Application CLIENT (mobile) — /api/client/...
|--------------------------------------------------------------------------
| Jetons distincts de ceux des agents : un jeton client est refusé sur les routes agents
| (middleware "active") et inversement (middleware "client").
*/
Route::prefix('client')->group(function () {
    Route::get('/countries', [ClientTransferController::class, 'countries']);

    Route::middleware('throttle:client-auth')->group(function () {
        Route::post('/check', [ClientAuthController::class, 'check']);
        Route::post('/register', [ClientAuthController::class, 'register']);
        Route::post('/login', [ClientAuthController::class, 'login']);
        Route::post('/pin/reset', [ClientAuthController::class, 'resetPin']);
    });
    Route::post('/otp', [ClientAuthController::class, 'requestOtp'])->middleware('throttle:client-otp');

    Route::middleware(['auth:sanctum', 'client'])->group(function () {
        Route::get('/me', [ClientAuthController::class, 'me']);
        Route::patch('/me', [ClientAuthController::class, 'updateProfile']);
        Route::post('/pin', [ClientAuthController::class, 'changePin']);
        Route::post('/logout', [ClientAuthController::class, 'logout']);

        Route::post('/quote', [ClientTransferController::class, 'quote']);
        Route::get('/beneficiaries', [ClientTransferController::class, 'beneficiaries']);
        Route::get('/transfers', [ClientTransferController::class, 'index']);
        Route::post('/transfers', [ClientTransferController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/transfers/{transaction:uuid}', [ClientTransferController::class, 'show']);
        Route::post('/transfers/{transaction:uuid}/cancel', [ClientTransferController::class, 'cancel']);
        Route::get('/transfers/{transaction:uuid}/receipt/{kind}', [ClientTransferController::class, 'receipt'])
            ->where('kind', 'deposit|withdrawal');
    });
});
