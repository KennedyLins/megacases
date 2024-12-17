<?php

use Illuminate\Support\Facades\Route;
use Webkul\MoloniIntegration\Http\Controllers\MoloniController;

Route::prefix('api/moloni')->group(function () {
    Route::post('/process-cart', [MoloniController::class, 'processCart']);
    Route::get('/get-invoice-pdf', [MoloniController::class, 'getInvoicePDF']);
});
