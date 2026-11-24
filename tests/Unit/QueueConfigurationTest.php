<?php

test('database queue uses the default queue name and connection override', function () {
    putenv('DB_QUEUE_CONNECTION');
    unset($_ENV['DB_QUEUE_CONNECTION'], $_SERVER['DB_QUEUE_CONNECTION']);
    putenv('DB_QUEUE');
    unset($_ENV['DB_QUEUE'], $_SERVER['DB_QUEUE']);

    $queue = require config_path('queue.php');

    expect($queue['connections']['database']['connection'])->toBeNull()
        ->and($queue['connections']['database']['queue'])->toBe('default');
});
