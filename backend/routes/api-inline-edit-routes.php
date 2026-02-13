<?php

/**
 * Routes API pour l'édition inline
 * 
 * Ajouter ces routes dans routes/api.php
 */

use App\Http\Controllers\Api\TransactionInlineEditController;

// Routes protégées par authentification Sanctum
Route::middleware('auth:sanctum')->group(function () {
    
    // Mise à jour inline d'une transaction
    Route::patch('/transactions/{id}', [TransactionInlineEditController::class, 'update']);
    
    // Alternative avec PUT
    Route::put('/transactions/{id}', [TransactionInlineEditController::class, 'update']);
    
    // Mise à jour en masse (optionnel)
    Route::post('/transactions/bulk-update', [TransactionInlineEditController::class, 'bulkUpdate']);
    
});

/**
 * Si vous n'utilisez pas Sanctum, utilisez plutôt:
 * 
 * Route::middleware('auth:api')->group(function () {
 *     // ... routes
 * });
 * 
 * Ou sans authentification (déconseillé en production):
 * 
 * Route::patch('/transactions/{id}', [TransactionInlineEditController::class, 'update']);
 */
