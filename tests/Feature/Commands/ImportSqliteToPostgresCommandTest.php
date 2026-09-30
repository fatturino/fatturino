<?php

use App\Console\Commands\ImportSqliteToPostgresCommand;
use App\Console\Commands\VerifySqlitePostgresMigrationCommand;

it('converts legacy empty nullable temporal values to null without changing text values', function () {
    $command = app(ImportSqliteToPostgresCommand::class);
    $method = new ReflectionMethod($command, 'normalizeRow');

    $row = $method->invoke($command, [
        'due_date' => '',
        'sdi_received_at' => '',
        'notes' => '',
    ], [
        'due_date' => 'date',
        'sdi_received_at' => 'timestamp without time zone',
        'notes' => 'text',
    ], ['due_date', 'sdi_received_at']);

    expect($row['due_date'])->toBeNull()
        ->and($row['sdi_received_at'])->toBeNull()
        ->and($row['notes'])->toBe('');
});

it('treats legacy empty nullable temporal values as null when verifying hashes', function () {
    $command = app(VerifySqlitePostgresMigrationCommand::class);
    $method = new ReflectionMethod($command, 'normalizeValue');

    expect($method->invoke($command, '', 'date', true))->toBe('<null>')
        ->and($method->invoke($command, null, 'date', true))->toBe('<null>')
        ->and($method->invoke($command, '', 'text', false))->toBe('');
});
