<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('/_test/csrf-contract/web', fn() => response()->json(['ok' => true]));
        Route::put('/_test/csrf-contract/json', fn() => response()->json(['ok' => true]));
    });
});

test('authenticated web and JSON writes use Laravel testing CSRF behavior', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/_test/csrf-contract/web')
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->actingAs($user)
        ->putJson('/_test/csrf-contract/json')
        ->assertOk()
        ->assertJson(['ok' => true]);
});
