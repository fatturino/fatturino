<?php

use App\Services\InboundEventFingerprintService;

it('builds same fingerprint for same logical customer notification event', function () {
    $service = app(InboundEventFingerprintService::class);

    $event = 'customer-notification';
    $data = [
        'notification' => [
            'invoice_uuid' => 'abc-uuid',
            'type' => 'RC',
            'message' => ['foo' => 'bar'],
        ],
    ];

    $a = $service->build($event, $data, 'fingerprint-1');

    $data['notification']['message']['foo'] = 'baz';

    $b = $service->build($event, $data, 'fingerprint-1');

    expect($a)->toBe($b);
});
