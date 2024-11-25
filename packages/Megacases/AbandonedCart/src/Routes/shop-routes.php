<?php

use Illuminate\Support\Facades\Route;
use Megacases\AbandonedCart\Http\Controllers\Shop\AbandonedCartController;

Route::group(['middleware' => ['web', 'theme', 'locale', 'currency'], 'prefix' => 'abandonedcart'], function () {
    Route::get('', [AbandonedCartController::class, 'index'])->name('shop.abandonedcart.index');
});