<?php

use App\Livewire\AuditLog\Index as AuditLogIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Contacts\Create as ContactsCreate;
use App\Livewire\Contacts\Edit as ContactsEdit;
use App\Livewire\Contacts\Index as ContactsIndex;
use App\Livewire\CreditNotes\Create as CreditNotesCreate;
use App\Livewire\CreditNotes\Edit as CreditNotesEdit;
use App\Livewire\CreditNotes\Index as CreditNotesIndex;
use App\Livewire\Dashboard;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Invoices\Create as InvoicesCreate;
use App\Livewire\Invoices\Edit as InvoicesEdit;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Proforma\Create as ProformaCreate;
use App\Livewire\Proforma\Edit as ProformaEdit;
use App\Livewire\Proforma\Index as ProformaIndex;
use App\Livewire\PurchaseInvoices\Edit as PurchaseInvoicesEdit;
use App\Livewire\PurchaseInvoices\Index as PurchaseInvoicesIndex;
use App\Livewire\SelfInvoices\Create as SelfInvoicesCreate;
use App\Livewire\SelfInvoices\Edit as SelfInvoicesEdit;
use App\Livewire\SelfInvoices\Index as SelfInvoicesIndex;
use App\Livewire\Sequences\Index as SequencesIndex;
use App\Livewire\Settings\Company as CompanySettings;
use App\Livewire\Settings\Email as EmailSettings;
use App\Livewire\Settings\Invoice as InvoiceSettings;
use App\Livewire\Settings\Plugins as PluginsSettings;
use App\Livewire\Settings\SdiSettings;
use App\Livewire\Settings\Services as ServicesSettings;
use App\Livewire\Setup\Wizard as SetupWizard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root redirects to login (landing page is on the separate fatturino-web site)
Route::redirect('/', '/login');

// Guest-only routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/setup', SetupWizard::class)->name('setup');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/contacts', ContactsIndex::class)->name('contacts.index');
    Route::get('/contacts/create', ContactsCreate::class)->name(
        'contacts.create',
    );
    Route::get('/contacts/{contact}/edit', ContactsEdit::class)->name(
        'contacts.edit',
    );

    Route::get('/sell-invoices', InvoicesIndex::class)->name(
        'sell-invoices.index',
    );
    Route::get('/sell-invoices/create', InvoicesCreate::class)->name(
        'sell-invoices.create',
    );
    Route::get('/sell-invoices/{invoice}/edit', InvoicesEdit::class)->name(
        'sell-invoices.edit',
    );

    Route::get('/purchase-invoices', PurchaseInvoicesIndex::class)->name(
        'purchase-invoices.index',
    );
    Route::get(
        '/purchase-invoices/{purchaseInvoice}/edit',
        PurchaseInvoicesEdit::class,
    )->name('purchase-invoices.edit');

    Route::get('/self-invoices', SelfInvoicesIndex::class)->name(
        'self-invoices.index',
    );
    Route::get('/self-invoices/create', SelfInvoicesCreate::class)->name(
        'self-invoices.create',
    );
    Route::get(
        '/self-invoices/{selfInvoice}/edit',
        SelfInvoicesEdit::class,
    )->name('self-invoices.edit');

    Route::get('/credit-notes', CreditNotesIndex::class)->name(
        'credit-notes.index',
    );
    Route::get('/credit-notes/create', CreditNotesCreate::class)->name(
        'credit-notes.create',
    );
    Route::get('/credit-notes/{creditNote}/edit', CreditNotesEdit::class)->name(
        'credit-notes.edit',
    );
    Route::get('/proforma', ProformaIndex::class)->name('proforma.index');
    Route::get('/proforma/create', ProformaCreate::class)->name(
        'proforma.create',
    );
    Route::get('/proforma/{proformaInvoice}/edit', ProformaEdit::class)->name(
        'proforma.edit',
    );
    Route::get('/sequences', SequencesIndex::class)->name('sequences.index');

    Route::get('/company-settings', CompanySettings::class)->name(
        'settings.company',
    );
    Route::get('/invoice-settings', InvoiceSettings::class)->name(
        'settings.invoice',
    );
    Route::get('/imports', ImportsIndex::class)->name('imports.index');
    Route::get('/electronic-invoice-settings', app()->bound('route.settings.openapi') ? app('route.settings.openapi') : SdiSettings::class)->name('settings.openapi');
    Route::get('/email-settings', EmailSettings::class)->name('settings.email');
    Route::get('/services', ServicesSettings::class)->name('settings.services');
    Route::get('/plugins', PluginsSettings::class)->name('settings.plugins');

    Route::get('/audit-log', AuditLogIndex::class)
        ->middleware('can:viewAuditLog')
        ->name('audit-log.index');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
