<?php

use Illuminate\Support\Facades\Route;
use Weboldalnet\CommerceBarion\Http\Controllers\BarionCallbackController;

Route::middleware(['web'])->group(function () {
    Route::get('/commerce/barion/return', [BarionCallbackController::class, 'handleReturn'])->name('commerce.barion.return');
    Route::post('/commerce/barion/callback', [BarionCallbackController::class, 'handleCallback'])->name('commerce.barion.callback');
});
