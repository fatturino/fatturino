<?php

use Illuminate\Support\Facades\Blade;

it('renders every destination in a task-oriented sidebar hierarchy', function () {
    $html = Blade::render('<x-shell.sidebar />');

    expect($html)
        ->toContain('aria-label="Navigazione principale"')
        ->toContain('>Riepilogo</span>')
        ->toContain('>Documenti</p>')
        ->toContain('>Fatture di vendita</span>')
        ->toContain('>Fatture di acquisto</span>')
        ->toContain('>Clienti e fornitori</span>')
        ->toContain('>Dati azienda</span>')
        ->toContain('>Diagnostica</span>')
        ->toContain('href="' . route('dashboard') . '"')
        ->toContain('href="' . route('contacts.index') . '"')
        ->toContain('href="' . route('settings.advanced') . '"');

    expect(substr_count($html, '<svg'))->toBe(15);
    expect(strpos($html, '>Documenti</p>'))
        ->toBeLessThan(strpos($html, '>Dati</p>'))
        ->toBeLessThan(strpos($html, '>Impostazioni</p>'));
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

it('renders every sidebar label from the active locale', function () {
    app()->setLocale('en');

    $html = Blade::render('<x-shell.sidebar />');

    expect($html)
        ->toContain('aria-label="Main navigation"')
        ->toContain('aria-label="Close menu"')
        ->toContain('>Overview</span>')
        ->toContain('>Documents</p>')
        ->toContain('>Sales invoices</span>')
        ->toContain('>Purchase invoices</span>')
        ->toContain('>Customers &amp; suppliers</span>')
        ->toContain('>E-invoicing</span>')
        ->toContain('>Diagnostics</span>')
        ->not->toContain('app.nav.');
});
