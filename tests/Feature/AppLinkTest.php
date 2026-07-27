<?php

use Illuminate\Support\Facades\Blade;

it('uses Livewire navigation for internal application links', function () {
    $html = Blade::render('<x-app-link href="/dashboard" class="nav-link">Dashboard</x-app-link>');

    expect($html)
        ->toContain('href="/dashboard"')
        ->toContain('class="nav-link"')
        ->toContain('wire:navigate')
        ->toContain('>Dashboard</a>');
});

it('keeps external links as browser navigations', function () {
    $html = Blade::render('<x-app-link href="https://example.test" external target="_blank" rel="noopener">External</x-app-link>');

    expect($html)
        ->toContain('href="https://example.test"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener"')
        ->not->toContain('wire:navigate');
});

it('keeps downloads and explicitly reloaded links outside Livewire navigation', function () {
    $download = Blade::render('<x-app-link href="/invoices/1/pdf" download>PDF</x-app-link>');
    $fullReload = Blade::render('<x-app-link href="/reports/export" full-reload>Export</x-app-link>');

    expect($download)
        ->toContain('download')
        ->not->toContain('wire:navigate');
    expect($fullReload)->not->toContain('wire:navigate');
});

it('uses application links instead of JavaScript redirects for linked table cells', function () {
    $html = Blade::render(<<<'BLADE'
        <x-table-row
            :headers="[['key' => 'number', 'label' => 'Numero']]"
            :row="['id' => 42, 'number' => 'FV-42']"
            link="/sell-invoices/{id}/edit"
        />
    BLADE);

    expect($html)
        ->toContain('href="/sell-invoices/42/edit"')
        ->toContain('wire:navigate')
        ->not->toContain('window.location');
});

it('does not leave direct application anchors or location redirects outside the link component', function () {
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($views as $view) {
        if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
            continue;
        }

        $path = $view->getPathname();
        if (str_contains($path, '/vendor/') || str_contains($path, '/errors/') || str_ends_with($path, '/components/app-link.blade.php')) {
            continue;
        }

        expect(file_get_contents($path))
            ->not->toMatch('/<a(?=\s|>)/i')
            ->not->toContain('window.location');
    }
});
