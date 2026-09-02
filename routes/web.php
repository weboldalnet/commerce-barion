<?php

use Illuminate\Support\Facades\Route;
use Weboldalnet\CommerceBarion\Http\Controllers\BarionCallbackController;
use Weboldalnet\CommerceBarion\Http\Controllers\Admin\BarionSettingController;

Route::middleware(['web'])->group(function () {
    Route::get('/commerce/barion/return', [BarionCallbackController::class, 'handleReturn'])->name('commerce.barion.return');
    Route::post('/commerce/barion/callback', [BarionCallbackController::class, 'handleCallback'])->name('commerce.barion.callback');
});

// FIGYELEM: a platformon 'admin_share' a middleware alias, nem 'admin'.
Route::domain(getAdminDomain())
    ->middleware(['web', 'admin_share', 'auth:admin'])
    ->prefix('webshop/barion')
    ->name('admin.webshop.barion.')
    ->group(function () {
        Route::get('/settings', [BarionSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [BarionSettingController::class, 'update'])->name('settings.update');
        Route::post('/test-connection', [BarionSettingController::class, 'testConnection'])->name('test-connection');
    });
