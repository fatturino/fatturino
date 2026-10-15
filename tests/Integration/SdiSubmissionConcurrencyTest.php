<?php

use App\Enums\InvoiceStatus;
use App\Enums\SdiSubmissionStatus;
use App\Models\FiscalDocument;
use App\Models\SdiOutboundSubmission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    Artisan::call('migrate:fresh', ['--force' => true]);

    DB::statement('create table sdi_integration_remote_calls (id bigserial primary key, submission_id bigint not null, created_at timestamp(0) without time zone not null)');
});

test('two independent PostgreSQL processes submit a document only once', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $document = FiscalDocument::factory()->create([
        'status' => InvoiceStatus::XmlValidated,
        'number' => 'CONCURRENT-1',
    ]);
    $barrierDirectory = storage_path('framework/testing/sdi-concurrency-'.Str::uuid());
    mkdir($barrierDirectory, 0700, true);
    $readyPaths = ["{$barrierDirectory}/one.ready", "{$barrierDirectory}/two.ready"];
    $releasePath = "{$barrierDirectory}/release";
    $processes = [];

    try {
        foreach ($readyPaths as $readyPath) {
            $process = proc_open([
                PHP_BINARY,
                base_path('tests/Integration/sdi_submission_worker.php'),
                (string) $document->id,
                $readyPath,
                $releasePath,
            ], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path());

            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start a concurrent SDI submission worker.');
            }

            $processes[] = [$process, $pipes];
        }

        $deadline = microtime(true) + 10;
        while (count(array_filter($readyPaths, 'file_exists')) !== 2) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Concurrent workers did not reach the submission barrier.');
            }

            usleep(10_000);
        }

        touch($releasePath);

        foreach ($processes as [$process, $pipes]) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            expect(proc_close($process))->toBe(0, $stderr)
                ->and($stdout)->toContain('"success":false');
        }

        expect(DB::table('sdi_integration_remote_calls')->count())->toBe(1)
            ->and(SdiOutboundSubmission::query()->count())->toBe(1)
            ->and(SdiOutboundSubmission::query()->sole()->status)->toBe(SdiSubmissionStatus::OutcomeUnknown)
            ->and(SdiOutboundSubmission::query()->sole()->active_document_lock)->toBe((string) $document->id);
    } finally {
        foreach ($processes as [$process]) {
            if (is_resource($process)) {
                proc_terminate($process);
            }
        }

        foreach (glob("{$barrierDirectory}/*") ?: [] as $path) {
            unlink($path);
        }
        rmdir($barrierDirectory);
    }
});
