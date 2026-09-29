<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('fails when the SQLite migration source is missing', function () {
    $report = storage_path('app/testing-migration/missing-source.json');

    $exit = Artisan::call('database:migration-preflight', [
        '--source' => storage_path('app/testing-migration/missing.sqlite'),
        '--report' => $report,
    ]);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('SQLite migration source is missing or unreadable')
        ->and(File::json($report)['success'])->toBeFalse();

    File::deleteDirectory(dirname($report));
});

it('reports a valid SQLite source database', function () {
    $workdir = storage_path('app/testing-migration');
    File::ensureDirectoryExists($workdir);
    $source = $workdir . '/source.sqlite';
    $report = $workdir . '/preflight.json';

    $database = new SQLite3($source);
    foreach (
        [
            'users',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'settings',
            'sequences',
            'contacts',
            'fiscal_documents',
            'fiscal_documents_lines',
            'ei_outbound_logs',
            'ei_inbound_logs',
            'sdi_uuid_links',
            'payments',
            'personal_access_tokens',
            'document_events',
            'sdi_outbound_submissions',
            'sessions',
            'migrations',
        ] as $table
    ) {
        $database->exec("create table {$table} (id integer primary key)");
    }
    $database->close();

    $exit = Artisan::call('database:migration-preflight', [
        '--source' => $source,
        '--report' => $report,
    ]);

    expect($exit)->toBe(0)
        ->and(File::json($report)['success'])->toBeTrue();

    File::deleteDirectory($workdir);
});
