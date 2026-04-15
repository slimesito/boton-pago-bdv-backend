<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->group(function (): void {
    Route::post('/init', [PaymentController::class, 'init']);
    Route::post('/send-token', [PaymentController::class, 'sendToken']);
    Route::post('/process', [PaymentController::class, 'process']);
    Route::get('/groups', [PaymentController::class, 'groups']);
    Route::get('/{reference}/status', [PaymentController::class, 'status']);

    // Biopago redirect after payment — no auth middleware
    Route::get('/return', [PaymentController::class, 'return'])->withoutMiddleware(['auth', 'auth:sanctum']);
});
