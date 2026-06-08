<?php

use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\PlaidWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/plaid/webhook', [PlaidWebhookController::class, 'handleWebhook']);

// Transaction API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('transactions', TransactionController::class);
    Route::get('transactions/options', [TransactionController::class, 'options']);
    Route::get('transactions/statistics', [TransactionController::class, 'statistics']);
});
