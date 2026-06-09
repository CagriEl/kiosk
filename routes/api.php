<?php

use App\Http\Controllers\Api\KioskApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('kiosk')->group(function () {
    Route::get('/citizen/{identityNo}', [KioskApiController::class, 'citizen']);
    Route::get('/debts/{identityNo}', [KioskApiController::class, 'debts']);
    Route::post('/payment/qr', [KioskApiController::class, 'initiatePayment']);
    Route::match(['get', 'post'], '/payment/{transactionId}/status', [KioskApiController::class, 'paymentStatus']);
});
