<?php

use App\Contracts\EnvironmentCapabilities;
use App\Models\Sequence;
use App\Models\User;
use App\Services\DemoCapabilities;
use App\Services\UnrestrictedCapabilities;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('renders sequences as a Livewire page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sequences.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::settings.sequences')
        ->assertDontSee('data-page=', false);
});

it('creates and deletes a custom sequence', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    app()->instance(EnvironmentCapabilities::class, new UnrestrictedCapabilities);

    $component = Livewire::test('pages::settings.sequences')
        ->set('name', 'Preventivi 2026')
        ->set('type', 'quote')
        ->set('pattern', 'FV-{ANNO}-{SEQ}');

    $component->instance()->storeSequence();
    $sequence = Sequence::query()->where('name', 'Preventivi 2026')->firstOrFail();

    Livewire::test('pages::settings.sequences')->instance()->delete($sequence);

    expect(Sequence::find($sequence->id))->toBeNull();
});

it('keeps sequence mutations read-only in demo mode', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    app()->instance(EnvironmentCapabilities::class, new DemoCapabilities);

    $component = Livewire::test('pages::settings.sequences')
        ->set('name', 'Non consentita')
        ->set('type', 'sales')
        ->set('pattern', '{SEQ}');

    expect(fn () => $component->instance()->storeSequence())->toThrow(HttpException::class);

    expect(Sequence::query()->where('name', 'Non consentita')->exists())->toBeFalse();
});
