<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('renders imports as a Livewire page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::imports.index')
        ->assertDontSee('data-page=', false);
});

it('requires a file for the selected import type', function () {
    Livewire::test('pages::imports.index')
        ->call('import')
        ->assertHasErrors(['xmlFiles' => 'required']);
});

it('renders the import workflow, file constraints, and XML uploader', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('imports.index'))
        ->assertOk()
        ->assertSee('Importazioni')
        ->assertSee('Importa fatture di vendita')
        ->assertSee('Tipo import')
        ->assertSee('File XML, P7M o ZIP fino a 10 MB ciascuno.')
        ->assertSee('Avvia importazione')
        ->assertSee('I file vengono elaborati subito e non viene creato uno storico delle importazioni.')
        ->assertSee('accept=".xml,.p7m,.zip"', false)
        ->assertSee('multiple', false);
});

it('switches to the Fattura24 CSV workflow and resets import state', function () {
    Livewire::test('pages::imports.index')
        ->set('updateExisting', true)
        ->set('importResult', ['type' => 'xml_sales', 'stats' => [], 'errors' => []])
        ->set('importType', 'fattura24_contacts')
        ->assertSet('importType', 'fattura24_contacts')
        ->assertSet('updateExisting', false)
        ->assertSet('importResult', null)
        ->assertSee('Importa contatti da Fattura24')
        ->assertSee('Un file CSV o TXT fino a 10 MB.')
        ->assertSee('Aggiorna contatti esistenti');
});

it('requires a CSV file for Fattura24 contact imports', function () {
    Livewire::test('pages::imports.index')
        ->set('importType', 'fattura24_contacts')
        ->call('import')
        ->assertHasErrors(['csvFile' => 'required']);
});

it('rejects unsupported XML and CSV file types before importing', function () {
    Livewire::test('pages::imports.index')
        ->set('xmlFiles', [UploadedFile::fake()->create('fattura.pdf', 100, 'application/pdf')])
        ->call('import')
        ->assertHasErrors(['xmlFiles.0']);

    Livewire::test('pages::imports.index')
        ->set('importType', 'fattura24_contacts')
        ->set('csvFile', UploadedFile::fake()->create('contatti.pdf', 100, 'application/pdf'))
        ->call('import')
        ->assertHasErrors(['csvFile']);
});

it('renders import results with localized statistics, errors, and destination actions', function () {
    Livewire::test('pages::imports.index')
        ->set('importResult', [
            'type' => 'fattura24_contacts',
            'stats' => ['total' => 4, 'imported' => 2, 'updated' => 1, 'skipped' => 1, 'errors' => 1],
            'errors' => ['Riga 5: P. IVA non valida.'],
        ])
        ->assertSee('Import completato con elementi da verificare')
        ->assertSee('File o righe elaborati')
        ->assertSee('Contatti importati')
        ->assertSee('Contatti aggiornati')
        ->assertSee('Errori da verificare')
        ->assertSee('Riga 5: P. IVA non valida.')
        ->assertSee('Vai ai contatti');
});

it('renders a successful import result with the correct documents destination', function () {
    Livewire::test('pages::imports.index')
        ->set('importResult', [
            'type' => 'xml_purchase',
            'stats' => ['total' => 1, 'invoices_imported' => 1, 'contacts_created' => 1, 'skipped' => 0, 'errors' => 0],
            'errors' => [],
        ])
        ->assertSee('Import completato')
        ->assertSee('Fatture importate')
        ->assertSee('Contatti creati')
        ->assertSee('Vai alle fatture di acquisto');
});
