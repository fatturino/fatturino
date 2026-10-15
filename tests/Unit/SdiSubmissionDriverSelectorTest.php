<?php

use App\Contracts\SdiSubmissionDriver;
use App\Sdi\SdiSubmissionCommand;
use App\Sdi\SdiSubmissionResult;
use App\Services\SdiSubmissionDriverRegistry;
use App\Services\SdiSubmissionDriverSelector;

test('the legacy provider setting selects the compatible submission driver', function () {
    config()->set('sdi.driver', 'openapi');

    $selector = new SdiSubmissionDriverSelector(new SdiSubmissionDriverRegistry([new FakeSubmissionDriver]));

    expect($selector->selectForNewSubmission())->toBe('openapi_legacy');
});

test('an unknown submission driver fails before dispatch', function () {
    config()->set('sdi.driver', 'unknown');

    $selector = new SdiSubmissionDriverSelector(new SdiSubmissionDriverRegistry([new FakeSubmissionDriver]));

    $selector->selectForNewSubmission();
})->throws(InvalidArgumentException::class, 'is not registered');

class FakeSubmissionDriver implements SdiSubmissionDriver
{
    public function key(): string
    {
        return 'openapi_legacy';
    }

    public function submit(SdiSubmissionCommand $command): SdiSubmissionResult
    {
        return SdiSubmissionResult::unknown(null);
    }
}
