<?php

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

it('shows aggregate KPIs for the fiscal-year documents with a single result set', function () {
    $user = User::factory()->create();

    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'total_gross' => 10000,
    ]);
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'status' => 'sent',
        'payment_status' => 'overdue',
        'total_gross' => 20000,
    ]);
    SalesInvoice::factory()->create([
        'date' => now()->subYear()->toDateString(),
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'total_gross' => 99900,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('€ 300,00')
        ->assertSee('2 fatture')
        ->assertSee('€ 150,00')
        ->assertSee('2', false)
        ->assertSee('documenti aperti')
        ->assertSee('da saldare');
});

it('shows the payment filter only for payable document indexes', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('Pagamento');

    Livewire::test('pages::documents.index', ['type' => 'proforma'])
        ->assertDontSee('Pagamento');
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
        ->assertSee('>Azioni<', false)
        ->assertSee('Apri documento')
        ->assertSee('Segna incasso')
        ->assertSee('Invia email')
        ->assertSee('Verifica XML')
        ->assertSee('Invia a SDI')
        ->assertSee('Conferma invio SDI')
        ->assertSee('Questa azione è irreversibile.');
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
    $convertible = ProformaInvoice::factory()->create(['date' => now()->toDateString()]);
    $converted = ProformaInvoice::factory()->converted()->create(['date' => now()->toDateString()]);

    $this->actingAs($user)
        ->get('/proforma')
        ->assertOk()
        ->assertSee('Converti in fattura')
        ->assertSee(route('proforma.convert', $convertible), false)
        ->assertDontSee(route('proforma.convert', $converted), false);
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

it('supports selecting and clearing all documents on the visible page for future bulk actions', function () {
    $user = User::factory()->create();
    $first = SalesInvoice::factory()->create(['date' => now()->toDateString()]);
    $second = SalesInvoice::factory()->create(['date' => now()->toDateString()]);

    $this->actingAs($user);

    Livewire::test('pages::documents.index', ['type' => 'sales'])
        ->assertSee('id="select-page"', false)
        ->call('togglePageSelection', [$first->id, $second->id])
        ->assertSet('selected', [$first->id, $second->id])
        ->call('togglePageSelection', [$first->id, $second->id])
        ->assertSet('selected', []);
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
