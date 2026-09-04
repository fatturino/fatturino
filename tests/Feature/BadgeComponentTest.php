<?php

use Illuminate\Support\Facades\Blade;

it('renders compact Tailkit-style small pill badges with semantic variants', function () {
    $success = Blade::render('<x-badge value="Attivo" variant="success" />');
    $neutral = Blade::render('<x-badge value="Bozza" variant="neutral" class="ml-2" />');

    expect($success)
        ->toContain('rounded-full')
        ->toContain('px-2')
        ->toContain('py-1')
        ->toContain('text-xs')
        ->toContain('leading-4')
        ->toContain('font-semibold')
        ->toContain('background-color: color-mix')
        ->toContain('>Attivo</span>');

    expect($neutral)
        ->toContain('background-color: var(--color-base-200)')
        ->toContain('ml-2')
        ->toContain('>Bozza</span>');
});

it('preserves badge icons and consumer attributes', function () {
    $html = Blade::render('<x-badge value="Attivo" variant="success" icon="o-check-circle" aria-label="Servizio attivo" />');

    expect($html)
        ->toContain('aria-label="Servizio attivo"')
        ->toContain('<svg')
        ->toContain('>Attivo</span>');
});

it('renders dot and inverted badge variants through the shared component', function () {
    $dot = Blade::render('<x-badge value="Scaduta" variant="danger" dot />');
    $inverted = Blade::render('<x-badge value="3" inverted />');

    expect($dot)
        ->toContain('size-1.5 shrink-0 rounded-full bg-current')
        ->toContain('>Scaduta</span>');

    expect($inverted)
        ->toContain('background-color: rgba(255, 255, 255, 0.12)')
        ->toContain('color: var(--color-sidebar-text)')
        ->toContain('>3</span>');
});
