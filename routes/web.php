<?php

use App\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
Route::get('/kiosk', [KioskController::class, 'index']);
