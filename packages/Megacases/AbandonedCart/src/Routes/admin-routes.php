<?php

use Illuminate\Support\Facades\Route;
use Megacases\AbandonedCart\Http\Controllers\Admin\AbandonedCartController;

Route::group(['middleware' => ['web', 'admin'], 'prefix' => 'admin/abandonedcart'], function () {
    Route::controller(AbandonedCartController::class)->group(function () {
        Route::get('', 'index')->name('admin.abandonedcart.index');
    });
});