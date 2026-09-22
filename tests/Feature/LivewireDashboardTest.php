<?php

use App\Models\SalesInvoice;
use App\Models\User;
use App\Settings\CompanySettings;
use Livewire\Livewire;

it('renders the authenticated dashboard as a Livewire page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire('pages::dashboard')
        ->assertSee('Oggi')
        ->assertSee('Aggiornato ora')
        ->assertDontSee('data-page=', false);
});

it('requires authentication for the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('loads dashboard metrics for the fiscal year stored in session', function () {
    $user = User::factory()->create();
    $year = now()->year - 1;

    $this->actingAs($user)->withSession(['fiscal_year' => $year]);

    Livewire::test('pages::dashboard')
        ->assertSet('fiscalYear', $year)
        ->assertSet('isCurrentYear', false);
});

it('loads document dates when child models expose them as strings', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->actingAs($user);

    // SalesInvoice overrides the parent casts, so date fields can be strings.
    // Mounting must still serialize the dashboard data without calling format()
    // directly on the raw value.
    Livewire::test('pages::dashboard')->assertOk();
});

it('shows a drill-down financial summary with valid zero values', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'total_net' => 600000,
        'total_gross' => 600000,
        'total_paid' => 0,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Fatturato')
        ->assertSee('Da incassare')
        ->assertSee('Scaduto')
        ->assertSee('€ 6.000,00')
        ->assertSee('/sell-invoices?payment=open', false)
        ->assertSee('/sell-invoices?payment=overdue', false);
});

it('shows annual turnover net of VAT for the selected fiscal year', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'total_net' => 1000000,
        'total_vat' => 220000,
        'total_gross' => 1220000,
        'total_paid' => 1220000,
        'payment_status' => 'paid',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Fatturato')
        ->assertSee('€ 10.000,00')
        ->assertSee('da inizio anno')
        ->assertSee('IVA esclusa')
        ->assertSee('Proiezione anno');
});

it('shows net amounts and separate VAT in recent invoices and due dates', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_net' => 100000,
        'total_vat' => 22000,
        'total_gross' => 122000,
        'total_paid' => 0,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Documenti recenti')
        ->assertSee('€ 1.000,00')
        ->assertSee('IVA € 220,00');
});

it('orders operational attention by overdue, SDI-ready, and partial collection work', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'payment_status' => 'overdue', 'total_net' => 10000, 'total_gross' => 10000, 'total_paid' => 0]);
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'status' => 'xml_validated']);
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'payment_status' => 'partial', 'total_net' => 10000, 'total_gross' => 10000, 'total_paid' => 5000]);

    $this->actingAs($user);

    $html = Livewire::test('pages::dashboard')->html();

    expect($html)
        ->toContain('Richiede attenzione')
        ->toContain('Fatture scadute')
        ->toContain('Incassi parziali')
        ->toContain('/sell-invoices?payment=partial')
        ->and(strpos($html, 'Fatture scadute'))->toBeLessThan(strpos($html, 'Incassi parziali'));
});

it('shows upcoming due dates with their remaining balance, state, date, and edit route', function () {
    $user = User::factory()->create();
    $invoice = SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'payment_status' => 'partial',
        'total_net' => 10000,
        'total_gross' => 10000,
        'total_paid' => 4000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Prossime scadenze')
        ->assertSee('Urgente')
        ->assertSee('Scade tra 3 giorni')
        ->assertSee(now()->addDays(3)->format('d/m/Y'))
        ->assertSee('€ 60,00')
        ->assertSee(route('sell-invoices.edit', $invoice), false);
});

it('labels upcoming due dates by their temporal priority', function () {
    $states = [
        [-1, 'danger', 'Scaduta', 'Scaduta da 1 giorno'],
        [0, 'danger', 'Scade oggi', 'Pagamento previsto oggi'],
        [7, 'warning', 'Urgente', 'Scade tra 7 giorni'],
        [8, 'info', 'Imminente', 'Scade tra 8 giorni'],
        [31, 'default', 'Futura', 'Scade tra 31 giorni'],
        [null, 'default', 'Data da verificare', 'Nessuna data prevista'],
    ];
    $invoices = collect($states)->map(fn(array $state, int $index): array => [
        'id' => $index + 1,
        'contact' => 'Cliente ' . ($index + 1),
        'due_date' => now()->addDays($index + 1)->format('d/m/Y'),
        'remaining_balance' => 10000,
        'days_until_due' => $state[0],
        'due_tone' => $state[1],
        'due_label' => $state[2],
        'due_detail' => $state[3],
        'is_urgent' => in_array($state[0], [-1, 0, 7], true),
    ]);

    $this->view('components.dashboard.upcoming-due-dates', ['invoices' => $invoices])
        ->assertSee('Scaduta')
        ->assertSee('Scaduta da 1 giorno')
        ->assertSee('Scade oggi')
        ->assertSee('Pagamento previsto oggi')
        ->assertSee('Urgente')
        ->assertSee('Scade tra 7 giorni')
        ->assertSee('Imminente')
        ->assertSee('Scade tra 8 giorni')
        ->assertSee('Futura')
        ->assertSee('Scade tra 31 giorni')
        ->assertSee('Data da verificare');
});

it('distinguishes a future due date from urgent attention and reuses its complete row', function () {
    $user = User::factory()->create();
    $invoice = SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(8)->toDateString(),
        'payment_status' => 'unpaid',
        'total_net' => 12500,
        'total_gross' => 12500,
        'total_paid' => 0,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Nessuna priorità urgente')
        ->assertSee('La prossima scadenza è riportata qui sotto.')
        ->assertSee('0 urgenze')
        ->assertSee('Prossima scadenza')
        ->assertSee('Imminente')
        ->assertSee('Scade tra 8 giorni')
        ->assertSee(now()->addDays(8)->format('d/m/Y'))
        ->assertSee('€ 125,00')
        ->assertSee(route('sell-invoices.edit', $invoice), false)
        ->assertDontSee('Nessuna urgenza per ora');
});

it('counts a due date within seven days as urgent attention', function () {
    $user = User::factory()->create();
    $invoice = SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'payment_status' => 'unpaid',
        'total_net' => 10000,
        'total_gross' => 10000,
        'total_paid' => 0,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('1 urgenze')
        ->assertSee('Scadenza urgente')
        ->assertSee('Urgente')
        ->assertSee('Scade tra 3 giorni')
        ->assertSee(route('sell-invoices.edit', $invoice), false)
        ->assertDontSee('Nessuna priorità urgente');
});

it('guides a first-time user without treating zero values as an error', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Inizia dalla tua prima fattura')
        ->assertSee('Nessuna priorità urgente')
        ->assertSee('Nessuna scadenza aperta')
        ->assertSee('€ 0,00');
});

it('keeps dashboard actions consultative for a closed fiscal year', function () {
    $user = User::factory()->create();
    $year = now()->year - 1;
    SalesInvoice::factory()->create(['date' => now()->subYear()->toDateString(), 'payment_status' => 'overdue']);

    $this->actingAs($user)->withSession(['fiscal_year' => $year]);

    Livewire::test('pages::dashboard')
        ->assertSee("Visualizzazione in sola lettura per l'anno fiscale {$year}.", false)
        ->assertDontSee('Nuova fattura')
        ->assertSee('Consulta scadute');
});

it('shows fiscal and collection information for VAT accounting regimes', function () {
    $user = User::factory()->create();
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF01';
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('IVA incassata separata')
        ->assertSee('Saldo IVA')
        ->assertSee('Andamento fatturato');
});

it('renders the revenue comparison through Wirecharts', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'total_net' => 150000,
        'total_gross' => 150000,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('wirecharts', false)
        ->assertSee('wireChart(', false)
        ->assertDontSee('<polyline', false)
        ->assertSee('Proiezione anno');
});

it('provides accessible text alternatives for the revenue comparison', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'total_net' => 150000,
        'total_gross' => 150000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('id="revenue-chart-description"', false)
        ->assertSee("Valori mensili del fatturato al netto dell'IVA", false)
        ->assertSee('scope="col">Mese', false);
});

it('announces dashboard refresh state and keeps KPI metadata separated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('role="status"', false)
        ->assertSee('Aggiornamento dati in corso')
        ->assertSee('dashboard-kpi-meta', false)
        ->assertSee('dashboard-kpi-period', false);
});

it('keeps dashboard card metadata readable and labels the VAT summary', function () {
    $user = User::factory()->create();
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF01';
    $settings->save();
    SalesInvoice::factory()->create([
        'date' => now()->toDateString(),
        'payment_status' => 'unpaid',
        'due_date' => now()->addDays(8)->toDateString(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('dashboard-document-card', false)
        ->assertSee('due-date-card', false)
        ->assertSee('aria-labelledby="vat-summary-title"', false);
});

it('shows the forecast only for the active fiscal year with turnover', function () {
    $user = User::factory()->create();
    SalesInvoice::factory()->create(['date' => now()->toDateString(), 'total_net' => 100000, 'total_gross' => 100000]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee('Fatturati')
        ->assertSee('Proiezione anno')
        ->assertSee('Previsione');

    $this->withSession(['fiscal_year' => now()->subYear()->year])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Proiezione anno');
});

it('hides VAT information for the RF19 fiscal regime', function () {
    $user = User::factory()->create();
    $settings = app(CompanySettings::class);
    $settings->company_fiscal_regime = 'RF19';
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertDontSee('IVA incassata separata')
        ->assertDontSee('Saldo IVA')
        ->assertDontSee("Ritenute d'acconto");
});
