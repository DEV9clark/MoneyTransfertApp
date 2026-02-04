<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', [App\Http\Controllers\WebAuthController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\WebAuthController::class, 'logout'])->name('logout');

// Language Switch
Route::get('/lang/{locale}', [App\Http\Controllers\LanguageController::class, 'switch'])->name('lang.switch');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/clients', function () {
        return view('clients.index');
    })->name('clients.index');

    Route::get('/transactions', function () {
        return view('transactions.create');
    })->name('transactions.create');

    Route::get('api/clients/search', [App\Http\Controllers\Api\ClientController::class, 'search']);
    Route::get('api/transactions/stats', [App\Http\Controllers\Api\TransactionController::class, 'stats']);
    Route::get('api/transactions/pending-count', [App\Http\Controllers\Api\TransactionController::class, 'pendingCount']);
    Route::get('api/transactions/pending-list', [App\Http\Controllers\Api\TransactionController::class, 'pendingList']);
    Route::post('api/transactions/calculate-fees', [App\Http\Controllers\Api\TransactionController::class, 'calculateFees']);
    Route::post('api/transactions/verify', [App\Http\Controllers\Api\TransactionController::class, 'verifyCode']);
    Route::post('api/transactions', [App\Http\Controllers\Api\TransactionController::class, 'transfer']);
    Route::post('api/transactions/withdraw', [App\Http\Controllers\Api\TransactionController::class, 'withdraw']);

    // Tariffs
    Route::resource('tariffs', App\Http\Controllers\TariffController::class);
    Route::apiResource('api/clients', App\Http\Controllers\Api\ClientController::class);
    Route::get('api/transactions', [App\Http\Controllers\Api\TransactionController::class, 'history']);
});


