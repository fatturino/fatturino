<?php

use App\Models\User;
use App\Models\SalesInvoice;
use Livewire\Livewire;

it('renders the authenticated dashboard as a Livewire page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire('pages::dashboard')
        ->assertSee('Incassi, scadenze e documenti da chiudere')
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
