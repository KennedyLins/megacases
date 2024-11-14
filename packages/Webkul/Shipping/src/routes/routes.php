<?php

use Webkul\Shipping\Http\Controllers\CTTController;

Route::post('ctt/calculate-shipping', [CTTController::class, 'calculateShipping']);
Route::post('/ctt/create-shipment', [CTTController::class, 'createShipment']);
Route::get('/ctt/track-shipment/{trackingId}', [CTTController::class, 'trackShipment']);
