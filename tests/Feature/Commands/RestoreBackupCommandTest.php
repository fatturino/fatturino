<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('fails when restore source is missing', function () {
    Artisan::call('app:restore-backup', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('Provide exactly one source');
});

it('validates a local backup zip in dry-run mode', function () {
    $workdir = storage_path('app/testing-restore');
    File::ensureDirectoryExists($workdir);

    $zipPath = $workdir.'/backup.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('db-dumps/database.sql', "CREATE TABLE test_table (id INTEGER PRIMARY KEY);\n");
    $zip->addFromString('storage/app/public/example.txt', 'ok');
    $zip->addFromString('storage/app/private/documents/doc.xml', '<xml/>');
    $zip->close();

    $exit = Artisan::call('app:restore-backup', [
        '--file' => $zipPath,
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Backup archive validated');

    File::deleteDirectory($workdir);
});
