<?php

use App\Http\Controllers\Api\KioskApiController;
use Illuminate\Support\Facades\Route;

// Baylan ASPX başarı sayacı (Image beacon — API key yok)
Route::get('/kiosk/stats/hit', [KioskApiController::class, 'recordStatHit']);

// Kiosk heartbeat (API key yok — cihazdan her dakika gelir)
Route::get('/kiosk/heartbeat', [KioskApiController::class, 'heartbeat']);

Route::prefix('kiosk')->middleware('kiosk.key')->group(function () {
    Route::get('/health', [KioskApiController::class, 'health']);
    Route::post('/stats/event', [KioskApiController::class, 'recordStatEvent']);
    Route::get('/payment-methods', [KioskApiController::class, 'paymentMethods']);
    Route::get('/receipt/{makbuzId}', [KioskApiController::class, 'receipt']);
    Route::get('/citizen/{identityNo}', [KioskApiController::class, 'citizen'])
        ->where('identityNo', '[0-9]{11}');
    Route::get('/sicil/{sicilNo}', [KioskApiController::class, 'sicilDetay'])
        ->where('sicilNo', '[0-9]{1,10}');
    Route::get('/debts/{identityNo}', [KioskApiController::class, 'debts'])
        ->where('identityNo', '[0-9]{11}');
    Route::post('/payment/bank', [KioskApiController::class, 'initiatePayment']);
    Route::post('/payment/{transactionId}/confirm', [KioskApiController::class, 'paymentStatus']);
    // Geriye dönük uyumluluk
    Route::post('/payment/qr', [KioskApiController::class, 'initiatePayment']);
    Route::match(['get', 'post'], '/payment/{transactionId}/status', [KioskApiController::class, 'paymentStatus']);
});
