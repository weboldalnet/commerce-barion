<?php

use App\Helpers\CustomPageHelper;

use App\Http\Controllers\Admin\CommerceBarion\CommerceBarionController;

Route::namespace('App\Http\Controllers\Site')->domain(getSiteDomain())->middleware('web', 'site_share')->group(function () {
    /** ----- Site route-ok ----- */

});

Route::namespace('App\Http\Controllers\Admin')->domain(getAdminDomain())->middleware('web', 'admin_share')->group(function () {

    Route::middleware('auth:admin')->group(function () {
        Route::namespace('CommerceBarion')->group(function () {
            /** ----- Admin route-ok ----- */

        });
    });
});
