<?php

use App\Support\Ulid;

it('generates lowercase crockford ulids', function () {
    $id = Ulid::generate();

    expect($id)->toHaveLength(26)
        ->and($id)->toBe(strtolower($id))
        ->and($id)->toMatch('/^[0-9a-z]{26}$/');
});
