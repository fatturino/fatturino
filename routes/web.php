<?php

use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\CreditNotesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceSettingsController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OpenApiController;
use App\Http\Controllers\OpenApiSettingsController;
use App\Http\Controllers\OpenApiWebhookController;
use App\Http\Controllers\ProformaInvoicesController;
use App\Http\Controllers\PurchaseInvoicesController;
use App\Http\Controllers\SalesInvoicesController;
use App\Http\Controllers\SelfInvoicesController;
use App\Http\Controllers\SequencesController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SetupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root redirects to login (landing page is on the separate fatturino-web site)
Route::redirect('/', '/login');

// Webhook (no auth)
Route::post('/api/openapi/webhook', [OpenApiWebhookController::class, 'handle'])->name('openapi.webhook');

// Guest-only routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login')->defaults('title', 'Accedi');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/setup', [SetupController::class, 'show'])->name('setup')->defaults('title', 'Configurazione');
    Route::post('/setup/step', [\App\Http\Controllers\Api\SetupWizardController::class, 'store'])->name('setup.step');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->defaults('title', 'Dashboard');

    Route::post('/fiscal-year', function (Request $request) {
        $request->validate(['year' => 'required|integer|min:2000|max:'.(now()->year + 1)]);
        session(['fiscal_year' => (int) $request->year]);

        return back();
    })->name('fiscal-year.update');

    // Contacts
    Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index')->defaults('title', 'Contatti');
    Route::get('/contacts/create', [ContactsController::class, 'create'])->name('contacts.create')
        ->defaults('title', 'Nuovo Contatto')
        ->defaults('breadcrumbs', [['label' => 'Contatti', 'url' => '/contacts'], ['label' => 'Nuovo']]);
    Route::post('/contacts', [ContactsController::class, 'store'])->name('contacts.store');
    Route::get('/contacts/{contact}/edit', [ContactsController::class, 'edit'])->name('contacts.edit')
        ->defaults('title', 'Modifica Contatto')
        ->defaults('breadcrumbs', [['label' => 'Contatti', 'url' => '/contacts'], ['label' => 'Modifica']]);
    Route::put('/contacts/{contact}', [ContactsController::class, 'update'])->name('contacts.update');

    // Sales Invoices
    Route::get('/sell-invoices', [SalesInvoicesController::class, 'index'])->name('sell-invoices.index')->defaults('title', 'Fatture di Vendita');
    Route::get('/sell-invoices/create', [SalesInvoicesController::class, 'create'])->name('sell-invoices.create')
        ->defaults('title', 'Nuova Fattura')
        ->defaults('breadcrumbs', [['label' => 'Fatture di Vendita', 'url' => '/sell-invoices'], ['label' => 'Nuova']]);
    Route::post('/sell-invoices', [SalesInvoicesController::class, 'store'])->name('sell-invoices.store');
    Route::get('/sell-invoices/{invoice}/edit', [SalesInvoicesController::class, 'edit'])->name('sell-invoices.edit')
        ->defaults('title', 'Modifica Fattura')
        ->defaults('breadcrumbs', [['label' => 'Fatture di Vendita', 'url' => '/sell-invoices'], ['label' => 'Modifica']]);
    Route::put('/sell-invoices/{invoice}', [SalesInvoicesController::class, 'update'])->name('sell-invoices.update');
    Route::get('/sell-invoices/{invoice}/xml', [SalesInvoicesController::class, 'downloadXml'])->name('sell-invoices.download-xml');
    Route::get('/sell-invoices/{invoice}/pdf', [SalesInvoicesController::class, 'downloadPdf'])->name('sell-invoices.download-pdf');
    Route::post('/sell-invoices/{invoice}/validate-xml', [SalesInvoicesController::class, 'validateXml'])->name('sell-invoices.validate-xml');
    Route::post('/sell-invoices/{invoice}/send-sdi', [SalesInvoicesController::class, 'sendToSdi'])->name('sell-invoices.send-sdi');
    Route::post('/sell-invoices/{invoice}/send-email', [SalesInvoicesController::class, 'sendEmail'])->name('sell-invoices.send-email');
    Route::post('/sell-invoices/{invoice}/payments', [SalesInvoicesController::class, 'recordPayment'])->name('sell-invoices.record-payment');
    Route::put('/sell-invoices/{invoice}/payments/{payment}', [SalesInvoicesController::class, 'updatePayment'])
        ->whereNumber('payment')
        ->name('sell-invoices.update-payment');
    Route::delete('/sell-invoices/{invoice}/payments/{payment}', [SalesInvoicesController::class, 'deletePayment'])
        ->whereNumber('payment')
        ->name('sell-invoices.delete-payment');

    // Purchase Invoices
    Route::get('/purchase-invoices', [PurchaseInvoicesController::class, 'index'])->name('purchase-invoices.index')->defaults('title', 'Fatture di Acquisto');
    Route::get('/purchase-invoices/{purchaseInvoice}/edit', [PurchaseInvoicesController::class, 'edit'])->name('purchase-invoices.edit')
        ->defaults('title', 'Modifica Fattura di Acquisto')
        ->defaults('breadcrumbs', [['label' => 'Fatture di Acquisto', 'url' => '/purchase-invoices'], ['label' => 'Modifica']]);
    Route::put('/purchase-invoices/{purchaseInvoice}', [PurchaseInvoicesController::class, 'update'])->name('purchase-invoices.update');
    Route::post('/purchase-invoices/{purchaseInvoice}/payments', [PurchaseInvoicesController::class, 'recordPayment'])->name('purchase-invoices.record-payment');
    Route::put('/purchase-invoices/{purchaseInvoice}/payments/{payment}', [PurchaseInvoicesController::class, 'updatePayment'])
        ->whereNumber('payment')
        ->name('purchase-invoices.update-payment');
    Route::delete('/purchase-invoices/{purchaseInvoice}/payments/{payment}', [PurchaseInvoicesController::class, 'deletePayment'])
        ->whereNumber('payment')
        ->name('purchase-invoices.delete-payment');

    // Self Invoices
    Route::get('/self-invoices', [SelfInvoicesController::class, 'index'])->name('self-invoices.index')->defaults('title', 'Autofatture');
    Route::get('/self-invoices/create', [SelfInvoicesController::class, 'create'])->name('self-invoices.create')
        ->defaults('title', 'Nuova Autofattura')
        ->defaults('breadcrumbs', [['label' => 'Autofatture', 'url' => '/self-invoices'], ['label' => 'Nuova']]);
    Route::post('/self-invoices', [SelfInvoicesController::class, 'store'])->name('self-invoices.store');
    Route::get('/self-invoices/{selfInvoice}/edit', [SelfInvoicesController::class, 'edit'])->name('self-invoices.edit')
        ->defaults('title', 'Modifica Autofattura')
        ->defaults('breadcrumbs', [['label' => 'Autofatture', 'url' => '/self-invoices'], ['label' => 'Modifica']]);
    Route::put('/self-invoices/{selfInvoice}', [SelfInvoicesController::class, 'update'])->name('self-invoices.update');
    Route::get('/self-invoices/{selfInvoice}/xml', [SelfInvoicesController::class, 'downloadXml'])->name('self-invoices.download-xml');
    Route::get('/self-invoices/{selfInvoice}/pdf', [SelfInvoicesController::class, 'downloadPdf'])->name('self-invoices.download-pdf');
    Route::post('/self-invoices/{selfInvoice}/validate-xml', [SelfInvoicesController::class, 'validateXml'])->name('self-invoices.validate-xml');
    Route::post('/self-invoices/{selfInvoice}/send-sdi', [SelfInvoicesController::class, 'sendToSdi'])->name('self-invoices.send-sdi');
    Route::post('/self-invoices/{selfInvoice}/send-email', [SelfInvoicesController::class, 'sendEmail'])->name('self-invoices.send-email');
    Route::post('/self-invoices/{selfInvoice}/payments', [SelfInvoicesController::class, 'recordPayment'])->name('self-invoices.record-payment');
    Route::put('/self-invoices/{selfInvoice}/payments/{payment}', [SelfInvoicesController::class, 'updatePayment'])
        ->whereNumber('payment')
        ->name('self-invoices.update-payment');
    Route::delete('/self-invoices/{selfInvoice}/payments/{payment}', [SelfInvoicesController::class, 'deletePayment'])
        ->whereNumber('payment')
        ->name('self-invoices.delete-payment');

    // Credit Notes
    Route::get('/credit-notes', [CreditNotesController::class, 'index'])->name('credit-notes.index')->defaults('title', 'Note di Credito');
    Route::get('/credit-notes/create', [CreditNotesController::class, 'create'])->name('credit-notes.create')
        ->defaults('title', 'Nuova Nota di Credito')
        ->defaults('breadcrumbs', [['label' => 'Note di Credito', 'url' => '/credit-notes'], ['label' => 'Nuova']]);
    Route::post('/credit-notes', [CreditNotesController::class, 'store'])->name('credit-notes.store');
    Route::get('/credit-notes/{creditNote}/edit', [CreditNotesController::class, 'edit'])->name('credit-notes.edit')
        ->defaults('title', 'Modifica Nota di Credito')
        ->defaults('breadcrumbs', [['label' => 'Note di Credito', 'url' => '/credit-notes'], ['label' => 'Modifica']]);
    Route::put('/credit-notes/{creditNote}', [CreditNotesController::class, 'update'])->name('credit-notes.update');
    Route::get('/credit-notes/{creditNote}/xml', [CreditNotesController::class, 'downloadXml'])->name('credit-notes.download-xml');
    Route::post('/credit-notes/{creditNote}/validate-xml', [CreditNotesController::class, 'validateXml'])->name('credit-notes.validate-xml');
    Route::post('/credit-notes/{creditNote}/send-sdi', [CreditNotesController::class, 'sendToSdi'])->name('credit-notes.send-sdi');

    // Proforma
    Route::get('/proforma', [ProformaInvoicesController::class, 'index'])->name('proforma.index')->defaults('title', 'Proforma');
    Route::get('/proforma/create', [ProformaInvoicesController::class, 'create'])->name('proforma.create')
        ->defaults('title', 'Nuova Proforma')
        ->defaults('breadcrumbs', [['label' => 'Proforma', 'url' => '/proforma'], ['label' => 'Nuova']]);
    Route::post('/proforma', [ProformaInvoicesController::class, 'store'])->name('proforma.store');
    Route::get('/proforma/{proformaInvoice}/edit', [ProformaInvoicesController::class, 'edit'])->name('proforma.edit')
        ->defaults('title', 'Modifica Proforma')
        ->defaults('breadcrumbs', [['label' => 'Proforma', 'url' => '/proforma'], ['label' => 'Modifica']]);
    Route::put('/proforma/{proformaInvoice}', [ProformaInvoicesController::class, 'update'])->name('proforma.update');

    // Sequences
    Route::get('/sequences', [SequencesController::class, 'index'])->name('sequences.index')->defaults('title', 'Sequenze');
    Route::post('/sequences', [SequencesController::class, 'store'])->middleware('capability:manage-sequences')->name('sequences.store');
    Route::put('/sequences/{sequence}', [SequencesController::class, 'update'])->middleware('capability:manage-sequences')->name('sequences.update');
    Route::delete('/sequences/{sequence}', [SequencesController::class, 'destroy'])->middleware('capability:manage-sequences')->name('sequences.destroy');

    // Settings
    Route::get('/company-settings', [CompanySettingsController::class, 'index'])->name('settings.company')->defaults('title', 'Dati Azienda');
    Route::put('/company-settings', [CompanySettingsController::class, 'update'])->middleware('capability:edit-company-settings')->name('settings.company.update');
    Route::get('/ateco/search', [CompanySettingsController::class, 'atecoSearch'])->name('ateco.search');
    Route::get('/invoice-settings', [InvoiceSettingsController::class, 'index'])->name('settings.invoice')->defaults('title', 'Impostazioni Fatture');
    Route::put('/invoice-settings', [InvoiceSettingsController::class, 'update'])->middleware('capability:edit-invoice-settings')->name('settings.invoice.update');
    Route::get('/email-settings', [EmailSettingsController::class, 'index'])->name('settings.email')->defaults('title', 'Template Email');
    Route::put('/email-settings', [EmailSettingsController::class, 'update'])->middleware('capability:edit-email-settings')->name('settings.email.update');
    Route::post('/email-settings/test', [EmailSettingsController::class, 'testConnection'])->name('settings.email.test');
    Route::get('/services', [ServicesController::class, 'index'])->name('settings.services')->defaults('title', 'Servizi');
    Route::put('/services/backup', [ServicesController::class, 'updateBackup'])->middleware('capability:manage-backup-settings')->name('settings.services.backup');
    Route::put('/services/monitoring', [ServicesController::class, 'updateMonitoring'])->middleware('capability:manage-monitoring-settings')->name('settings.services.monitoring');
    Route::post('/services/test-connection', [ServicesController::class, 'testConnection'])->name('settings.services.test');
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index')->defaults('title', 'Import');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');

    // Electronic Invoice
    Route::get('/electronic-invoice-settings', [OpenApiSettingsController::class, 'index'])->name('settings.openapi')->defaults('title', 'Fatturazione Elettronica');

    // OpenAPI API endpoints (JSON responses for SPA)
    Route::post('/api/openapi/save', [OpenApiController::class, 'save'])->middleware('capability:edit-sdi-settings');
    Route::post('/api/openapi/activate', [OpenApiController::class, 'activate'])->middleware('capability:edit-sdi-settings');
    Route::post('/api/openapi/deactivate', [OpenApiController::class, 'deactivate'])->middleware('capability:edit-sdi-settings');
    Route::post('/api/openapi/check-connection', [OpenApiController::class, 'checkConnection']);
    Route::post('/api/openapi/simulate-webhook', [OpenApiController::class, 'simulateWebhook']);
    Route::post('/api/openapi/acknowledge-conservation', [OpenApiController::class, 'acknowledgeConservation']);

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
