<?php

use Webkul\Shipping\Http\Controllers\CTTController;

Route::prefix('api')->group(function () {
    Route::post('/ctt/create', [CTTController::class, 'createShipment']);
    Route::post('/ctt/close', [CTTController::class, 'closeShipment']);
});
