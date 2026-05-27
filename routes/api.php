<?php

use App\Http\Controllers\Api\SalesInvoiceCreateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/sales-invoices', [SalesInvoiceCreateController::class, 'store']);
});
