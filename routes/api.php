<?php

use App\Http\Controllers\Api\AtecoSearchController;
use App\Http\Controllers\Api\SalesInvoiceCreateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/ateco/search', AtecoSearchController::class);
        Route::post('/sales-invoices', [SalesInvoiceCreateController::class, 'store']);
    });
});
