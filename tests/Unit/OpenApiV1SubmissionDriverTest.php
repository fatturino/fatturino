<?php

use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use App\Services\OpenApiV1SubmissionDriver;
use App\Settings\OpenApiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(OpenApiSettings::class);
    $settings->api_token = 'test-token';
    $settings->sandbox = true;
    $settings->save();
});

test('V1 driver accepts only the documented success envelope with a valid remote reference', function () {
    Http::fake([
        nativeSubmitUrl() => Http::response(nativeFixture('success'), 200),
    ]);

    $result = v1Driver()->submit(submissionCommand());

    expect($result->accepted)->toBeTrue()
        ->and($result->remoteReference)->toBe('remote-reference-001')
        ->and($result->remoteFileId)->toBe('remote-file-001')
        ->and($result->message)->toBe('Il provider ha accettato il documento.');
});

test('V1 driver normalizes a valid remote reference', function () {
    Http::fake([
        nativeSubmitUrl() => Http::response(['success' => true, 'data' => ['uuid' => ' remote-reference-002 ']], 200),
    ]);

    $result = v1Driver()->submit(submissionCommand());

    expect($result->accepted)->toBeTrue()
        ->and($result->remoteReference)->toBe('remote-reference-002');
});

foreach ([
    'missing reference' => [200, nativeFixture('missing_reference')],
    'uuid null' => [200, ['success' => true, 'data' => ['uuid' => null]]],
    'uuid number' => [200, ['success' => true, 'data' => ['uuid' => 99]]],
    'missing success flag' => [200, ['data' => ['uuid' => 'remote-reference-003']]],
    'false success flag' => [200, ['success' => false, 'data' => ['uuid' => 'remote-reference-003']]],
    'data list' => [200, ['success' => true, 'data' => ['remote-reference-003']]],
    'ambiguous success flag' => [200, nativeFixture('ambiguous_success')],
    'unexpected created status' => [201, nativeFixture('success')],
    'unexpected accepted status' => [202, nativeFixture('success')],
    'empty response' => [204, ''],
    'malformed json' => [200, 'not-json'],
    'json scalar' => [200, 'accepted'],
    'json list' => [200, [['success' => true]]],
] as $case => [$status, $body]) {
    test("V1 driver fails closed for invalid or ambiguous success payload: {$case}", function () use ($status, $body) {
        Http::fake([nativeSubmitUrl() => Http::response($body, $status)]);

        $result = v1Driver()->submit(submissionCommand());

        expect($result->outcomeIsUnknown())->toBeTrue()
            ->and($result->accepted)->toBeFalse()
            ->and($result->definitivelyRejected)->toBeFalse()
            ->and($result->httpStatus)->toBe($status);
    });
}

foreach ([
    'bad request' => [400, 'rejection_400'],
    'payment required' => [402, 'rejection_402'],
    'unprocessable entity' => [422, 'rejection_422'],
] as $case => [$status, $fixture]) {
    test("V1 driver rejects only documented complete rejection envelopes: {$case}", function () use ($status, $fixture) {
        Http::fake([nativeSubmitUrl() => Http::response(nativeFixture($fixture), $status)]);

        $result = v1Driver()->submit(submissionCommand());

        expect($result->definitivelyRejected)->toBeTrue()
            ->and($result->accepted)->toBeFalse()
            ->and($result->errorCode)->toBeNull()
            ->and($result->httpStatus)->toBe($status)
            ->and($result->message)->toBe('Il provider ha rifiutato il documento prima dell’accettazione.');
    });
}

foreach ([
    'missing error' => [422, nativeFixture('incomplete_rejection')],
    'blank message' => [400, ['success' => false, 'message' => ' ', 'error' => 230, 'data' => null]],
    'non-null data' => [422, ['success' => false, 'message' => 'failed', 'error' => 802, 'data' => []]],
    'unauthorized' => [401, nativeFixture('rejection_400')],
    'forbidden' => [403, nativeFixture('rejection_400')],
    'not found' => [404, nativeFixture('rejection_400')],
    'conflict' => [409, nativeFixture('rejection_400')],
    'rate limited' => [429, nativeFixture('rejection_400')],
    'redirect' => [302, nativeFixture('rejection_400')],
    'server error' => [500, nativeFixture('rejection_400')],
    'service unavailable' => [503, nativeFixture('rejection_400')],
] as $case => [$status, $body]) {
    test("V1 driver fails closed for incomplete rejection envelopes and unexpected statuses: {$case}", function () use ($status, $body) {
        Http::fake([nativeSubmitUrl() => Http::response($body, $status)]);

        $result = v1Driver()->submit(submissionCommand());

        expect($result->outcomeIsUnknown())->toBeTrue()
            ->and($result->httpStatus)->toBe($status);
    });
}

foreach (['timeout', 'rate-limit', 'server-error'] as $scenario) {
    test("V1 driver makes exactly one request without automatic retries after an ambiguous outcome: {$scenario}", function () use ($scenario) {
        $requests = 0;
        Http::fake(function () use ($scenario, &$requests) {
            $requests++;
            if ($scenario === 'timeout') {
                throw new ConnectionException('timeout');
            }

            return Http::response(['message' => 'unavailable'], $scenario === 'rate-limit' ? 429 : 503);
        });

        $result = v1Driver()->submit(submissionCommand());

        expect($result->outcomeIsUnknown())->toBeTrue();
        if ($scenario === 'timeout') {
            expect($requests)->toBe(1);
        } else {
            Http::assertSentCount(1);
        }
        if ($scenario !== 'timeout') {
            Http::assertSent(function ($request) {
                return $request->url() === nativeSubmitUrl()
                    && $request->body() === '<xml />'
                    && $request->hasHeader('Authorization', 'Bearer test-token')
                    && ! $request->hasHeader('Idempotency-Key')
                    && ! $request->hasHeader('X-Correlation-Id')
                    && ! $request->hasHeader('X-Filename');
            });
        }
    });
}

test('V1 driver never propagates sensitive remote content', function () {
    Http::fake([nativeSubmitUrl() => Http::response(nativeFixture('sensitive_remote_content'), 422)]);

    $result = v1Driver()->submit(submissionCommand());

    expect(json_encode($result))->not->toContain('SENSITIVE_REMOTE_MESSAGE_DO_NOT_LEAK')
        ->not->toContain('SENSITIVE_REMOTE_CODE_DO_NOT_LEAK');
});

test('accepted submission results require a non-blank remote reference', function () {
    expect(fn () => SdiSubmissionResult::accepted('  ', null, null))
        ->toThrow(InvalidArgumentException::class);
});

test('V1 fixtures remain protocol-only and free of forbidden content', function () {
    $contents = '';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../fixtures/openapi-sdi/v1')) as $file) {
        if ($file->isFile()) {
            $contents .= file_get_contents($file->getPathname());
        }
    }

    expect($contents)->not->toMatch('/<\/?FatturaElettronica/i')
        ->not->toMatch('/\b(?:IT)?\d{11}\b/')
        ->not->toMatch('/\b[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]\b/')
        ->not->toContain('Bearer ')
        ->not->toContain('IBAN');
});

function v1Driver(): OpenApiV1SubmissionDriver
{
    return app(OpenApiV1SubmissionDriver::class);
}

function nativeSubmitUrl(): string
{
    return 'https://test.sdi.openapi.it/invoices';
}

function nativeFixture(string $name): mixed
{
    $fixtureRoot = __DIR__.'/../fixtures/openapi-sdi/v1';
    $manifest = json_decode(file_get_contents($fixtureRoot.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $path = $fixtureRoot.'/'.$manifest['cases'][$name];

    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

function submissionCommand(): SdiSubmissionCommand
{
    return new SdiSubmissionCommand('<xml />', 1, 'submission-1', 'IT123.xml');
}
