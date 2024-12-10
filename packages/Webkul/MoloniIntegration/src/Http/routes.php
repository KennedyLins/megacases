<?php

use Illuminate\Support\Facades\Route;
use Webkul\MoloniIntegration\Http\Controllers\MoloniController;

Route::prefix('api/moloni')->group(function () {
    Route::post('/invoice', [MoloniController::class, 'createInvoice']);
});
