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

it('rejects archives without a PostgreSQL SQL dump', function () {
    $workdir = storage_path('app/testing-restore');
    File::ensureDirectoryExists($workdir);

    $zipPath = $workdir.'/backup.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('backup/database.sqlite', 'SQLite format 3');
    $zip->addFromString('storage/app/public/example.txt', 'ok');
    $zip->close();

    $exit = Artisan::call('app:restore-backup', [
        '--file' => $zipPath,
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('Database entry not found or ambiguous');

    File::deleteDirectory($workdir);
});

it('runs PostgreSQL-only cold restore before application migrations', function () {
    $coldRestore = File::get(base_path('docker/entrypoint.d/13-cold-restore.sh'));
    $migration = File::get(base_path('docker/entrypoint.d/15-migrate.sh'));

    expect($coldRestore)
        ->toContain('RESTORE_BACKUP_S3_KEY')
        ->toContain('--s3-key-is-full-path')
        ->toContain('--cold')
        ->toContain('.cold-restore-complete')
        ->toContain('postgresql)')
        ->and($migration)
        ->toContain('artisan migrate --force --no-interaction')
        ->not->toContain('sqlite');
});
