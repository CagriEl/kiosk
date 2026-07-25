<?php

use App\Http\Controllers\Api\KioskApiController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\KioskYonetimController;
use Illuminate\Support\Facades\Route;

Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
Route::get('/kiosk', [KioskController::class, 'index']);

// Eski adresler → şifreli yönetim
Route::redirect('/rapor', '/kiosk-yonetim/rapor');
Route::redirect('/baylan-ie', '/kiosk-yonetim');
Route::get('/baylan-ie/{any}', function () {
    return redirect('/kiosk-yonetim');
})->where('any', '.*');

// Kiosk yönetim (şifreli): rapor + kurulum dosyaları
Route::prefix('kiosk-yonetim')->group(function () {
    Route::get('/giris', [KioskYonetimController::class, 'loginForm'])->name('yonetim.login');
    Route::post('/giris', [KioskYonetimController::class, 'login'])->name('yonetim.login.post');

    Route::middleware('kiosk.yonetim')->group(function () {
        Route::get('/', [KioskYonetimController::class, 'index'])->name('yonetim.index');
        Route::post('/cikis', [KioskYonetimController::class, 'logout'])->name('yonetim.logout');
        Route::get('/rapor', [KioskYonetimController::class, 'report'])->name('yonetim.report');
        Route::get('/dosya/{file}', [KioskYonetimController::class, 'download'])
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('yonetim.download');
    });
});

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
