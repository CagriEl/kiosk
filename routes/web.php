<?php

use App\Http\Controllers\Api\KioskApiController;
use App\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
Route::get('/kiosk', [KioskController::class, 'index']);

// Kiosk API — web rotaları (paylaşımlı hosting /public altında güvenilir erişim)
Route::prefix('api/kiosk')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));
    Route::get('/citizen/{identityNo}', [KioskApiController::class, 'citizen']);
    Route::get('/debts/{identityNo}', [KioskApiController::class, 'debts']);
    Route::post('/payment/bank', [KioskApiController::class, 'initiatePayment']);
    Route::post('/payment/{transactionId}/confirm', [KioskApiController::class, 'paymentStatus']);
});
