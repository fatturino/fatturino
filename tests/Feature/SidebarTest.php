<?php

use Illuminate\Support\Facades\Blade;

it('renders an icon next to every sidebar navigation link', function () {
    $html = Blade::render('<x-shell.sidebar />');

    expect($html)
        ->toContain('aria-label="Navigazione principale"')
        ->toContain('>Dashboard</span>')
        ->toContain('>Contatti</span>')
        ->toContain('>Avanzate</span>')
        ->toContain('href="'.route('dashboard').'"')
        ->toContain('href="'.route('contacts.index').'"')
        ->toContain('href="'.route('settings.advanced').'"');

    expect(substr_count($html, '<svg'))->toBe(15);
});

it('renders an accessible mobile drawer that traps focus only while open', function () {
    $html = Blade::render('<x-shell.sidebar />');

    expect($html)
        ->toContain('id="app-sidebar"')
        ->toContain('x-trap.inert.noscroll="sidebarOpen && !isDesktop"')
        ->toContain(':inert="!isDesktop && !sidebarOpen"')
        ->toContain(':aria-hidden="(!isDesktop && !sidebarOpen).toString()"')
        ->toContain('x-ref="sidebarClose"')
        ->toContain('aria-label="Chiudi menu"')
        ->toContain('closeSidebar(true)')
        ->toContain('min-h-11');
});
