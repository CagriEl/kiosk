<?php

use App\Http\Controllers\Api\KioskApiController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\KioskReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
Route::get('/kiosk', [KioskController::class, 'index']);
Route::get('/rapor', KioskReportController::class)->name('kiosk.report');

// Statik kurulum paketi: public/baylan-ie/
Route::redirect('/baylan-ie', '/baylan-ie/index.html');

// Kiosk API — web rotaları (paylaşımlı hosting /public altında güvenilir erişim)
Route::prefix('api/kiosk')->middleware('kiosk.key')->group(function () {
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

    Route::post('/water/card-read', [KioskApiController::class, 'waterCardRead']);
    Route::get('/water/kontor-options', [KioskApiController::class, 'waterKontorOptions']);
    Route::get('/water/{vendor}/subscriber/{aboneNo}', [KioskApiController::class, 'waterSubscriber']);
    Route::get('/water/{vendor}/invoices/{aboneNo}', [KioskApiController::class, 'waterInvoices']);
    Route::post('/water/calculate-kontor', [KioskApiController::class, 'waterCalculateKontor']);
    Route::post('/water/pay-invoices', [KioskApiController::class, 'waterPayInvoices']);
    Route::post('/water/pay-invoices/{transactionId}/confirm', [KioskApiController::class, 'waterConfirmInvoicePayment']);
    Route::post('/water/advance-load', [KioskApiController::class, 'waterAdvanceLoad']);
    Route::post('/water/kontor/pay', [KioskApiController::class, 'waterInitiateKontor']);
    Route::post('/water/kontor/{transactionId}/confirm', [KioskApiController::class, 'waterConfirmKontor']);
    Route::post('/baylan/open', [KioskApiController::class, 'openBaylan']);
});
