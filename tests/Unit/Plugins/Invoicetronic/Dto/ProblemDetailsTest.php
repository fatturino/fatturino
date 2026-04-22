<?php

use Fatturino\FeInvoicetronic\Http\Dto\ProblemDetails;

it('parses full RFC 7807 payload', function () {
    $problem = ProblemDetails::fromArray([
        'title' => 'Validation Error',
        'status' => 422,
        'detail' => 'The XML file is missing required fields',
    ], '{"title":"Validation Error"}');

    expect($problem->title)->toBe('Validation Error')
        ->and($problem->status)->toBe(422)
        ->and($problem->detail)->toBe('The XML file is missing required fields')
        ->and($problem->rawBody)->toBe('{"title":"Validation Error"}');
});

it('handles missing optional fields gracefully', function () {
    $problem = ProblemDetails::fromArray(['status' => 500]);

    expect($problem->title)->toBe('Error')
        ->and($problem->detail)->toBe('')
        ->and($problem->status)->toBe(500);
});

it('uses message field as fallback for detail', function () {
    $problem = ProblemDetails::fromArray([
        'title' => 'Error',
        'status' => 400,
        'message' => 'Something went wrong',
    ]);

    expect($problem->detail)->toBe('Something went wrong');
});

it('preserves raw body for logging', function () {
    $raw = '{"title":"Error","status":422,"detail":"Bad input"}';
    $problem = ProblemDetails::fromArray(json_decode($raw, true), $raw);

    expect($problem->rawBody)->toBe($raw);
});
