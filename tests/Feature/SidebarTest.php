<?php

use Illuminate\Support\Facades\Blade;

it('renders an icon next to every sidebar navigation link', function () {
    $html = Blade::render('<x-shell.sidebar />');

    expect($html)
        ->toContain('aria-label="Navigazione principale"')
        ->toContain('>Dashboard</span>')
        ->toContain('>Contatti</span>')
        ->toContain('>Avanzate</span>')
        ->toContain('href="' . route('dashboard') . '"')
        ->toContain('href="' . route('contacts.index') . '"')
        ->toContain('href="' . route('settings.advanced') . '"');

    expect(substr_count($html, '<svg'))->toBe(15);
});
