<?php

test('database queue uses the central connection only in Cloud mode', function () {
    putenv('FATTURINO_CLOUD=true');
    unset($_ENV['FATTURINO_CLOUD'], $_SERVER['FATTURINO_CLOUD']);
    putenv('DB_QUEUE_CONNECTION');
    unset($_ENV['DB_QUEUE_CONNECTION'], $_SERVER['DB_QUEUE_CONNECTION']);

    try {
        $queue = require config_path('queue.php');

        expect($queue['connections']['database']['connection'])->toBe('central');
    } finally {
        putenv('FATTURINO_CLOUD');
        unset($_ENV['FATTURINO_CLOUD'], $_SERVER['FATTURINO_CLOUD']);
    }
});

test('database queue retains its default connection outside Cloud mode', function () {
    putenv('FATTURINO_CLOUD=false');
    unset($_ENV['FATTURINO_CLOUD'], $_SERVER['FATTURINO_CLOUD']);
    putenv('DB_QUEUE_CONNECTION');
    unset($_ENV['DB_QUEUE_CONNECTION'], $_SERVER['DB_QUEUE_CONNECTION']);

    try {
        $queue = require config_path('queue.php');

        expect($queue['connections']['database']['connection'])->toBeNull();
    } finally {
        putenv('FATTURINO_CLOUD');
        unset($_ENV['FATTURINO_CLOUD'], $_SERVER['FATTURINO_CLOUD']);
    }
});
