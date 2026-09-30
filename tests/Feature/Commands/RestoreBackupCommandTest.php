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

    $zipPath = $workdir . '/backup.zip';
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

it('recognizes a SQLite database file independently from the runtime connection', function () {
    $workdir = storage_path('app/testing-restore');
    File::ensureDirectoryExists($workdir);

    $sqlitePath = $workdir . '/database.sqlite';
    $database = new SQLite3($sqlitePath);
    $database->exec('create table restored_table (id integer primary key)');
    $database->close();

    $zipPath = $workdir . '/backup.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFile($sqlitePath, 'backup/database.sqlite');
    $zip->addFromString('storage/app/public/example.txt', 'ok');
    $zip->close();

    $exit = Artisan::call('app:restore-backup', [
        '--file' => $zipPath,
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('backup/database.sqlite (sqlite)');

    File::deleteDirectory($workdir);
});

it('rejects archives containing both SQLite and SQL database representations', function () {
    $workdir = storage_path('app/testing-restore');
    File::ensureDirectoryExists($workdir);

    $zipPath = $workdir . '/ambiguous.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('database.sqlite', 'SQLite format 3');
    $zip->addFromString('db-dumps/database.sql', 'select 1;');
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

it('runs cold restore before migration and automatically cuts over a restored SQLite source on PostgreSQL', function () {
    $coldRestore = File::get(base_path('docker/entrypoint.d/13-cold-restore.sh'));
    $migration = File::get(base_path('docker/entrypoint.d/15-migrate.sh'));

    expect($coldRestore)
        ->toContain('RESTORE_BACKUP_S3_KEY')
        ->toContain('--s3-key-is-full-path')
        ->toContain('.cold-restore-complete')
        ->and($migration)
        ->toContain('cold_restored_sqlite=true')
        ->toContain('database:import-sqlite-to-postgres');
});
