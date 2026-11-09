<?php

use App\Enums\F24PaymentStatus;
use App\Models\F24Payment;
use App\Models\User;
use App\Services\F24PaymentWriter;
use Livewire\Livewire;

it('requires authentication for the f24 index', function () {
    $this->get(route('f24.index'))->assertRedirect(route('login'));
});

it('renders the f24 index for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('f24.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::f24.index')
        ->assertSee(__('app.f24.title'));
});

it('creates an f24 payment with lines through the writer', function () {
    $payment = app(F24PaymentWriter::class)->save(null, [
        'source' => 'manual',
        'status' => 'paid',
        'paid_at' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'lines' => [
            [
                'section' => 'erario',
                'category' => 'irpef',
                'tribute_code' => '4001',
                'reference_year' => (string) (now()->year - 1),
                'installment_or_month' => '0101',
                'debit_cents' => 125000,
                'credit_cents' => 0,
                'label' => 'IRPEF - saldo',
            ],
            [
                'section' => 'erario',
                'category' => 'iva',
                'tribute_code' => '6031',
                'reference_year' => (string) now()->year,
                'debit_cents' => 32050,
                'credit_cents' => 0,
            ],
        ],
    ]);

    expect($payment->status)->toBe(F24PaymentStatus::Paid)
        ->and($payment->public_id)->toMatch('/^[0-9a-z]{26}$/')
        ->and($payment->public_id)->toBe(strtolower($payment->public_id))
        ->and($payment->total_debit_cents)->toBe(157050)
        ->and($payment->balance_cents)->toBe(157050)
        ->and($payment->lines)->toHaveCount(2);
});

it('persists a provided lowercase public_id from import draft', function () {
    $publicId = '01jf24testulid000000000001';

    $payment = app(F24PaymentWriter::class)->save(null, [
        'public_id' => strtoupper($publicId),
        'source' => 'ocr',
        'status' => 'paid',
        'paid_at' => now()->toDateString(),
        'lines' => [[
            'section' => 'erario',
            'category' => 'irpef',
            'tribute_code' => '4001',
            'debit_cents' => 1000,
            'credit_cents' => 0,
        ]],
    ]);

    expect($payment->public_id)->toBe($publicId);
});

it('marks a planned f24 payment as paid from the index', function () {
    $user = User::factory()->create();
    $payment = F24Payment::factory()->withIrpefLine(50000)->create([
        'status' => F24PaymentStatus::Planned,
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::f24.index')
        ->call('markPaid', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(F24PaymentStatus::Paid)
        ->and($payment->fresh()->paid_at)->not->toBeNull();
});

it('renders the kebab action menu for planned f24 payments on the index', function () {
    $user = User::factory()->create();
    $payment = F24Payment::factory()->withIrpefLine(50000)->create([
        'status' => F24PaymentStatus::Planned,
        'due_date' => now()->addDays(5)->toDateString(),
        'source_file_path' => 'f24/'.now()->year.'/demo/quietanza.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('f24.index'))
        ->assertOk()
        ->assertSee('aria-label="'.__('app.f24.actions_for', ['label' => 'IRPEF - saldo']).'"', false)
        ->assertSee('x-teleport="body"', false)
        ->assertSee('x-ref="menu"', false)
        ->assertSee(__('app.f24.edit'))
        ->assertSee(__('app.f24.mark_paid'))
        ->assertSee(__('app.f24.open_attachment'))
        ->assertSee(__('app.common.delete'))
        ->assertSee(route('f24.edit', $payment), false)
        ->assertSee(route('f24.attachment', ['path' => $payment->source_file_path]), false);
});

it('hides mark paid for already paid f24 payments on the index', function () {
    $user = User::factory()->create();
    F24Payment::factory()->paid()->withIrpefLine(20000)->create([
        'paid_at' => now()->toDateString(),
        'due_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('f24.index'))
        ->assertOk()
        ->assertSee(__('app.f24.edit'))
        ->assertDontSee(__('app.f24.mark_paid'))
        ->assertSee(__('app.common.delete'));
});

it('saves an f24 payment from the livewire form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::f24.form')
        ->set('status', 'planned')
        ->set('due_date', now()->addDays(3)->toDateString())
        ->set('lines.0.tribute_code', '4033')
        ->set('lines.0.category', 'irpef')
        ->set('lines.0.section', 'erario')
        ->set('lines.0.reference_year', (string) now()->year)
        ->set('lines.0.debit_euros', '1.000,00')
        ->set('lines.0.label', 'IRPEF acconto')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('f24.index'));

    $payment = F24Payment::query()->latest('id')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(F24PaymentStatus::Planned)
        ->and($payment->total_debit_cents)->toBe(100000)
        ->and($payment->lines->first()->tribute_code)->toBe('4033');
});

it('shows f24 summary metrics on the dashboard', function () {
    $user = User::factory()->create();
    F24Payment::factory()->paid()->withIrpefLine(20000)->create([
        'paid_at' => now()->toDateString(),
        'due_date' => now()->toDateString(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertSee(__('app.f24.dashboard_title'));
});

it('keeps import review as a human confirmation step without auto-saving', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    session()->put('f24_import_draft', [
        'paid_at' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'protocol_number' => null,
        'source_file_path' => 'f24/'.now()->year.'/demo/quietanza.pdf',
        'source_file_name' => 'quietanza.pdf',
        'source_file_mime' => 'application/pdf',
        'ocr_raw' => ['method' => 'pdftotext'],
        'ocr_warning' => __('app.f24.ocr_partial'),
        'lines' => [[
            'section' => 'erario',
            'category' => 'iva',
            'tribute_code' => '6001',
            'inps_causal' => '',
            'reference_year' => (string) now()->year,
            'installment_or_month' => '0101',
            'inps_period_from' => '',
            'inps_period_to' => '',
            'debit_euros' => '100,00',
            'credit_euros' => '',
            'label' => 'IVA mensile - gennaio',
        ]],
    ]);

    expect(F24Payment::count())->toBe(0);

    Livewire::test('pages::f24.form')
        ->assertSet('source', 'ocr')
        ->assertSet('lines.0.tribute_code', '6001')
        ->assertSee(__('app.f24.human_review_note'));

    expect(F24Payment::count())->toBe(0);
});
