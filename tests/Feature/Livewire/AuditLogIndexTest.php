<?php

use App\Livewire\AuditLog\Index as AuditLogIndex;
use App\Models\Invoice;
use App\Models\User;
use Livewire\Livewire;

test('non-admin user receives 403 on the audit log page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('audit-log.index'))
        ->assertForbidden();
});

test('admin user can access the audit log page', function () {
    $user = User::factory()->create(['is_admin' => true]);

    $this->actingAs($user)
        ->get(route('audit-log.index'))
        ->assertOk();
});

test('event filter restricts returned audits', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $invoice = Invoice::factory()->create();
    $invoice->update(['number' => 'changed']);

    Livewire::test(AuditLogIndex::class)
        ->set('filterEvent', 'created')
        ->assertOk()
        ->tap(function ($component) {
            $audits = $component->viewData('audits');
            foreach ($audits as $audit) {
                expect($audit->event)->toBe('created');
            }
        });
});

test('clearFilters resets all filter fields', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test(AuditLogIndex::class)
        ->set('filterEvent', 'updated')
        ->set('filterUserId', $admin->id)
        ->call('clearFilters')
        ->assertSet('filterEvent', null)
        ->assertSet('filterUserId', null);
});
