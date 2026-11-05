<?php

use App\Services\CompanyLogoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('company logo storage writes to the logos disk', function () {
    Storage::fake('logos');
    Storage::fake('public');

    $storage = app(CompanyLogoStorage::class);
    $path = $storage->store(UploadedFile::fake()->image('brand.png', 32, 32));

    expect($path)->toStartWith('logos/')
        ->and(Storage::disk('logos')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and($storage->exists($path))->toBeTrue()
        ->and($storage->get($path))->not->toBeNull();
});

test('company logo storage reads legacy public logos as fallback', function () {
    Storage::fake('logos');
    Storage::fake('public');

    $path = 'logos/legacy.png';
    Storage::disk('public')->put($path, 'legacy-bytes');

    $storage = app(CompanyLogoStorage::class);

    expect($storage->exists($path))->toBeTrue()
        ->and($storage->get($path))->toBe('legacy-bytes');
});

test('company logo storage delete removes both disks', function () {
    Storage::fake('logos');
    Storage::fake('public');

    $path = 'logos/to-delete.png';
    Storage::disk('logos')->put($path, 'new');
    Storage::disk('public')->put($path, 'old');

    app(CompanyLogoStorage::class)->delete($path);

    expect(Storage::disk('logos')->exists($path))->toBeFalse()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});
