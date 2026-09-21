<?php

use App\Enums\SdiStatus;
use App\Models\Contact;
use App\Models\ProformaInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

dataset('document index routes', [
    ['/sell-invoices', 'sales', 'Fatture di Vendita'],
    ['/purchase-invoices', 'purchase', 'Fatture di Acquisto'],
    ['/self-invoices', 'self', 'Autofatture'],
    ['/proforma', 'proforma', 'Proforma'],
    ['/credit-notes', 'credit', 'Note di Credito'],
]);

it('renders each document index as a Livewire page', function (string $url, string $type, string $title) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertSeeLivewire('pages::documents.index')
        ->assertSee($title)
        ->assertDontSee('data-page=', false);

    Livewire::test('pages::documents.index', ['type' => $type])
        ->assertSet('type', $type)
        ->assertDontSee('<h2 class="mt-1 text-2xl font-bold">' . $title . '</h2>', false)
        ->assertSee('id="document-search"', false);
})->with('document index routes');

it('filters sales invoices by search, status and fiscal year', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Cliente Cercato']);
    SalesInvoice::factory()->create(['contact_id' => $contact->id, 'number' => 'FV-2026-001', 'date' => now()->toDateString(), 'status' => 'draft']);
    SalesInvoice::factory()->create(['number' => 'FV-STORICA', 'date' => now()->subYear()->toDateString(), 'status' => 'draft']);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->set('search', 'Cliente Cercato')
        ->assertSee('FV-2026-001')
        ->assertDontSee('FV-STORICA')
        ->set('search', '')
        ->call('selectTab', 'draft')
        ->assertSee('FV-2026-001');
});

it('filters open sales invoices across unpaid, partial, and overdue payment states', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'number' => 'FV-APERTA-NON-PAGATA', 'payment_status' => 'unpaid']);
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'number' => 'FV-APERTA-PARZIALE', 'payment_status' => 'partial']);
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'number' => 'FV-APERTA-SCADUTA', 'payment_status' => 'overdue']);
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'number' => 'FV-PAGATA', 'payment_status' => 'paid']);

    $this->actingAs($user);

    Livewire::withQueryParams(['payment' => 'open'])
        ->test('pages::documents.index', ['type' => 'sales'])
        ->assertSet('payment', 'open')
        ->assertSee('FV-APERTA-NON-PAGATA')
        ->assertSee('FV-APERTA-PARZIALE')
        ->assertSee('FV-APERTA-SCADUTA')
        ->assertDontSee('FV-PAGATA');
});

it('shows a compact fiscal-year summary panel with payment operations', function () {
    $user = User::factory()->create();

    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'total_net' => 10000,
        'total_vat' => 2200,
        'total_gross' => 12200,
    ]);
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'status' => 'sent',
        'payment_status' => 'overdue',
        'total_net' => 20000,
        'total_vat' => 4400,
        'total_gross' => 24400,
    ]);
    SalesInvoice::factory()->create([
        'date' => now()->subYear()->toDateString(),
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'total_gross' => 99900,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('id="document-summary-title"', escape: false)
        ->assertSee('Riepilogo fatture')
        ->assertSee('Documenti')
        ->assertSee('2')
        ->assertSee('Imponibile')
        ->assertSee('€ 300,00')
        ->assertSee('IVA')
        ->assertSee('€ 66,00')
        ->assertSee('Da saldare')
        ->assertSee('1 scaduta')
        ->assertSee('Totale netto')
        ->assertSee('Imposte');
});

it('shows draft and sent metrics in the compact summary for non-payable documents', function () {
    $user = User::factory()->create();

    ProformaInvoice::factory()->create(['date' => now()->toDateString(), 'status' => 'draft']);
    ProformaInvoice::factory()->create(['date' => now()->toDateString(), 'status' => 'sent']);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'proforma'])
        ->assertSee('Riepilogo proforma')
        ->assertSee('Bozze')
        ->assertSee('1')
        ->assertSee('1 inviata')
        ->assertDontSee('Da saldare');
});

it('shows the payment filter only for payable document indexes', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('Pagamento');

    Livewire::test('pages::documents.index', ['type' => 'proforma'])
        ->assertDontSee('Pagamento');
});

it('distinguishes an empty document index from an empty filtered result', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('Nessuna fattura ancora registrata.')
        ->assertSee('Nuova fattura')
        ->set('search', 'nessuna-corrispondenza')
        ->assertSee('Nessun documento corrisponde ai filtri.')
        ->assertSee('Cancella filtri');
});

it('renders an accessible responsive document index with the operational invoice details', function () {
    $user = User::factory()->create();
    $invoice = SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'number' => 'FV-LINK-001',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('aria-sort="descending"', escape: false)
        ->assertSee("href=\"/sell-invoices/{$invoice->id}/edit\"", escape: false)
        ->assertSee('class="sr-only">Azioni</span>', escape: false)
        ->assertSee('class="hidden overflow-x-auto border-y border-border bg-white lg:block"', escape: false)
        ->assertSee('class="divide-y divide-border border-y border-border bg-white lg:hidden"', escape: false)
        ->assertSee('Totale')
        ->assertSee('Scadenza')
        ->assertSee('Nessuna')
        ->assertSee('Aggiornamento documenti in corso')
        ->call('sortBy', 'date')
        ->assertSee('aria-sort="ascending"', escape: false)
        ->call('sortBy', 'due_date')
        ->assertSet('sort', 'due_date');
});

it('renders the compatible document actions and gates the SDI send action by workflow state', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['email' => 'cliente@example.test']);
    SalesInvoice::factory()->create([
        'contact_id' => $contact->id,
        'date' => now()->toDateString(),
        'number' => 'FV-AZIONI-BOZZA',
        'status' => 'draft',
    ]);
    SalesInvoice::factory()->create([
        'contact_id' => $contact->id,
        'date' => now()->toDateString(),
        'number' => 'FV-AZIONI-VALIDATA',
        'status' => 'xml_validated',
    ]);

    $this->actingAs($user)
        ->get('/sell-invoices')
        ->assertOk()
        ->assertSee('aria-label="Azioni per FV-AZIONI-BOZZA"', false)
        ->assertSee('x-teleport="body"', false)
        ->assertSee('x-ref="menu"', false)
        ->assertSee('Apri documento')
        ->assertSee('Segna incasso')
        ->assertSee('Invia email')
        ->assertSee('Verifica XML')
        ->assertSee('Invia a SDI')
        ->assertSee('Conferma invio SDI')
        ->assertSee('Questa azione è irreversibile.')
        ->assertSee('class="modal-viewport"', false)
        ->assertSee('x-trap.inert.noscroll="paymentOpen"', false)
        ->assertSee('x-trap.inert.noscroll="confirmOpen"', false)
        ->assertSee('x-teleport="body"', false);
});

it('shows the XML and PDF download actions for sales invoices already sent to SDI', function () {
    $user = User::factory()->create();
    $invoice = SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'number' => 'FV-XML-INVIATA',
        'status' => 'sent',
        'sdi_status' => SdiStatus::Sent,
    ]);

    $this->actingAs($user)
        ->get('/sell-invoices')
        ->assertOk()
        ->assertSee('Scarica XML')
        ->assertSee("href=\"/sell-invoices/{$invoice->id}/xml\"", false)
        ->assertSee('Scarica PDF')
        ->assertSee("href=\"/sell-invoices/{$invoice->id}/pdf\"", false)
        ->assertSee('download', false);
});

it('shows the delete action only for unconverted proformas', function () {
    $user = User::factory()->create();
    $unconverted = ProformaInvoice::factory()->create(['date' => now()->toDateString()]);
    $converted = ProformaInvoice::factory()->converted()->create(['date' => now()->toDateString()]);

    $this->actingAs($user)
        ->get('/proforma')
        ->assertOk()
        ->assertSee("action: 'delete', id: {$unconverted->id}", false)
        ->assertDontSee("action: 'delete', id: {$converted->id}", false);
});

it('shows the conversion action only for convertible proformas', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $convertible = ProformaInvoice::factory()->create(['contact_id' => $contact->id, 'date' => now()->toDateString()]);
    $converted = ProformaInvoice::factory()->converted()->create(['date' => now()->toDateString()]);
    $linkable = SalesInvoice::factory()->create(['contact_id' => $contact->id, 'date' => now()->toDateString(), 'number' => 'FV-COLLEGABILE', 'total_gross' => 12000]);
    $otherCustomer = SalesInvoice::factory()->create(['date' => now()->toDateString(), 'number' => 'FV-ALTRO-CLIENTE']);

    $this->actingAs($user)
        ->get('/proforma')
        ->assertOk()
        ->assertSee('Converti in fattura')
        ->assertSee('Collega una fattura esistente')
        ->assertSee('Cerca per numero o importo')
        ->assertSee('FV-COLLEGABILE')
        ->assertDontSee('FV-ALTRO-CLIENTE')
        ->assertSee("action: 'convert', id: {$convertible->id}", false)
        ->assertDontSee("action: 'convert', id: {$converted->id}", false);
});

it('deletes an unconverted proforma through the document index', function () {
    $user = User::factory()->create();
    $proforma = ProformaInvoice::factory()->create(['date' => now()->toDateString()]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'proforma'])
        ->call('deleteProforma', $proforma->id);

    $this->assertDatabaseMissing('fiscal_documents', ['id' => $proforma->id]);
});

it('rejects deletion of a converted proforma through the document index', function () {
    $user = User::factory()->create();
    $proforma = ProformaInvoice::factory()->converted()->create(['date' => now()->toDateString()]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'proforma'])
        ->call('deleteProforma', $proforma->id)
        ->assertHasErrors(['proforma']);

    $this->assertDatabaseHas('fiscal_documents', ['id' => $proforma->id]);
});

it('does not expose incomplete bulk selection controls', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create(['date' => now()->toDateString()]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertDontSee('id="select-page"', false)
        ->assertDontSee('wire:model.live="selected"', false);
});

it('locks the document type and fiscal year to the server-side route context', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSet('type', 'sales')
        ->assertSet('fiscalYear', now()->year);

    expect(fn() => $component->set('type', 'self'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn() => $component->set('fiscalYear', now()->subYear()->year))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
