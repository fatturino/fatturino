<?php

use Illuminate\Support\Facades\Blade;

it('renders an Italian single-date picker with accessible calendar semantics', function () {
    $html = Blade::render('<div x-data="{ date: \"\" }"><x-date-picker label="Data documento" required x-model="date" /></div>');
    $script = file_get_contents(resource_path('views/components/date-picker-script.blade.php'));

    expect($html)
        ->toContain('Data documento')
        ->toContain('x-modelable="value"')
        ->toContain('x-model="date"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('role="grid"');

    expect($script)
        ->toContain("weekdays: ['L', 'M', 'M', 'G', 'V', 'S', 'D']")
        ->toContain("new Intl.DateTimeFormat('it-IT')")
        ->toContain('ArrowLeft')
        ->toContain('PageDown');
});

it('registers the Alpine date picker before Livewire starts Alpine', function () {
    $appLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $guestLayout = file_get_contents(resource_path('views/layouts/guest.blade.php'));
    $script = file_get_contents(resource_path('views/components/date-picker-script.blade.php'));

    expect($script)
        ->toContain("document.addEventListener('alpine:init'")
        ->toContain("Alpine.data('datePicker'")
        ->toContain("value: ''")
        ->toContain("get hasValue()")
        ->toContain("get monthLabel()")
        ->toContain('get days()');

    expect(strpos($appLayout, '<x-date-picker-script />'))->toBeLessThan(strpos($appLayout, '@livewireScripts'));
    expect(strpos($guestLayout, '<x-date-picker-script />'))->toBeLessThan(strpos($guestLayout, '@livewireScripts'));
});

it('renders range bindings and honors disabled and bounds configuration', function () {
    $html = Blade::render('<x-date-picker range start-wire-model="date_from" end-wire-model="date_to" min="2026-01-01" max="2026-12-31" disabled />');

    expect($html)
        ->toContain('wire:model="date_from"')
        ->toContain('wire:model="date_to"')
        ->toContain('range: true')
        ->toContain('2026-01-01')
        ->toContain('2026-12-31')
        ->toContain('disabled: true')
        ->toContain('date-picker__day--range');
});

it('replaces native date fields with the shared picker in all date selection surfaces', function () {
    $paths = [
        resource_path('views/pages/documents/sales/form.blade.php'),
        resource_path('views/pages/documents/proforma/form.blade.php'),
        resource_path('views/pages/documents/credit-note/form.blade.php'),
        resource_path('views/pages/documents/self-invoice/form.blade.php'),
        resource_path('views/pages/documents/purchase/form.blade.php'),
        resource_path('views/components/documents/document-action-center.blade.php'),
    ];

    foreach ($paths as $path) {
        expect(file_get_contents($path))
            ->toContain('<x-date-picker')
            ->not->toContain('type="date"');
    }
});
