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

    // Tariffs
    Route::resource('tariffs', App\Http\Controllers\TariffController::class);
});


