<?php

use App\Http\Controllers\Api\SalesInvoiceCreateController;
use App\Http\Controllers\Api\SetupWizardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {
    Route::post('/setup/step', [SetupWizardController::class, 'store']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/sales-invoices', [SalesInvoiceCreateController::class, 'store']);
});
