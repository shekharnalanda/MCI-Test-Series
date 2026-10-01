<?php

use App\Http\Controllers\AccountRecoveryController;
use Illuminate\Support\Facades\Route;

Route::get('/account-recovery', [AccountRecoveryController::class, 'show'])->name('mci.recovery');
Route::post('/account-recovery/send', [AccountRecoveryController::class, 'send'])->middleware('throttle:3,10')->name('mci.recovery.send');
Route::post('/account-recovery/verify', [AccountRecoveryController::class, 'verify'])->middleware('throttle:10,10')->name('mci.recovery.verify');
Route::middleware('auth')->group(function () {
    Route::get('/account/recovery-email', [AccountRecoveryController::class, 'contact'])->name('mci.recovery.contact');
    Route::post('/account/recovery-email/send', [AccountRecoveryController::class, 'sendContact'])->middleware('throttle:3,10')->name('mci.recovery.contact.send');
    Route::post('/account/recovery-email/verify', [AccountRecoveryController::class, 'verifyContact'])->middleware('throttle:10,10')->name('mci.recovery.contact.verify');
});
