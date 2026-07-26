<?php

use App\Models\User;
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
