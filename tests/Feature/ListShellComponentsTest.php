<?php

use Illuminate\Support\Facades\Blade;

it('renders page summary with actions, stats and footer', function () {
    $html = Blade::render(<<<'BLADE'
        <x-page-summary eyebrow="Anno" title="Riepilogo" title-id="summary-title">
            <x-slot:actions>
                <x-button label="Nuovo" link="/create" size="cta" />
            </x-slot:actions>
            <x-page-summary.stat label="Totale" value="12" value-id="summary-total" detail="Voci" />
            <x-slot:footer>
                <div class="footer-slot">extra</div>
            </x-slot:footer>
        </x-page-summary>
    BLADE);

    expect($html)
        ->toContain('aria-labelledby="summary-title"')
        ->toContain('id="summary-title"')
        ->toContain('id="summary-total" class="mt-1 text-xl font-semibold tabular-nums text-content">12</dd>')
        ->toContain('h-11')
        ->toContain('Nuovo')
        ->toContain('footer-slot');
});

it('renders empty state and loading banner primitives', function () {
    $empty = Blade::render('<x-empty-state title="Nessun risultato" body="Prova a cambiare filtri." />');
    $loading = Blade::render('<x-loading-banner target="search,status">Aggiornamento</x-loading-banner>');

    expect($empty)
        ->toContain('Nessun risultato')
        ->toContain('Prova a cambiare filtri.');

    expect($loading)
        ->toContain('wire:loading.flex')
        ->toContain('wire:target="search,status"')
        ->toContain('Aggiornamento');
});

it('exposes the cta button size used by list headers', function () {
    $html = Blade::render('<x-button label="Azione" size="cta" variant="secondary" />');

    expect($html)
        ->toContain('h-11')
        ->toContain('border border-border')
        ->toContain('Azione');
});
