<?php

use App\Http\Controllers\Api\KioskApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('kiosk')->group(function () {
    Route::get('/payment-methods', [KioskApiController::class, 'paymentMethods']);
    Route::get('/receipt/{makbuzId}', [KioskApiController::class, 'receipt']);
    Route::get('/citizen/{identityNo}', [KioskApiController::class, 'citizen']);
    Route::get('/debts/{identityNo}', [KioskApiController::class, 'debts']);
    Route::post('/payment/bank', [KioskApiController::class, 'initiatePayment']);
    Route::post('/payment/{transactionId}/confirm', [KioskApiController::class, 'paymentStatus']);
    // Geriye dönük uyumluluk
    Route::post('/payment/qr', [KioskApiController::class, 'initiatePayment']);
    Route::match(['get', 'post'], '/payment/{transactionId}/status', [KioskApiController::class, 'paymentStatus']);
});
