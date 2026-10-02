<?php

use App\Models\InAppNotification;
use App\Models\User;
use App\Services\InAppNotificationMessage;
use App\Services\InAppNotificationPublisher;
use App\Services\InAppNotificationQuery;
use App\Services\NotificationRecipientResolver;
use Livewire\Livewire;

test('publisher stores a notification once for the same recipient and dedupe key', function () {
    $user = User::factory()->create();
    $message = inAppNotificationMessage($user->id);

    $first = app(InAppNotificationPublisher::class)->publish($message);
    $second = app(InAppNotificationPublisher::class)->publish($message);

    expect($first->id)->toBe($second->id)
        ->and(InAppNotification::query()->count())->toBe(1)
        ->and($first->metadata)->toBe(['document_number' => 'FT-001']);
});

test('recipient resolver prefers the oldest administrator', function () {
    $regularUser = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    expect(app(NotificationRecipientResolver::class)->resolve()->id)->toBe($admin->id)
        ->and($regularUser->id)->not->toBe($admin->id);
});

test('query scopes notifications and counts unread records for the current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $first = app(InAppNotificationPublisher::class)->publish(inAppNotificationMessage($user->id, 'sdi-one'));
    app(InAppNotificationPublisher::class)->publish(inAppNotificationMessage($user->id, 'sdi-two'));
    app(InAppNotificationPublisher::class)->publish(inAppNotificationMessage($otherUser->id, 'other-user'));
    $first->markAsRead();

    $query = app(InAppNotificationQuery::class);
    $notifications = $query->paginateFor($user, ['filter' => 'unread']);
    $items = $notifications->items();

    expect($query->unreadCountFor($user))->toBe(1)
        ->and($items)->toHaveCount(1)
        ->and($items[0]->user_id)->toBe($user->id);
});

test('notifications can be marked read and unread idempotently', function () {
    $user = User::factory()->create();
    $notification = app(InAppNotificationPublisher::class)->publish(inAppNotificationMessage($user->id));

    expect($notification->markAsRead())->toBeTrue()
        ->and($notification->markAsRead())->toBeTrue()
        ->and($notification->fresh()->read_at)->not->toBeNull()
        ->and($notification->markAsUnread())->toBeTrue()
        ->and($notification->markAsUnread())->toBeTrue()
        ->and($notification->fresh()->read_at)->toBeNull();
});

test('the notification bell scopes interactions to the signed-in user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = app(InAppNotificationPublisher::class)->publish(inAppNotificationMessage($user->id));
    $otherNotification = app(InAppNotificationPublisher::class)->publish(inAppNotificationMessage($otherUser->id, 'other-user'));

    $this->actingAs($user);

    Livewire::test('shell.notifications')
        ->assertSet('unreadCount', 1)
        ->call('load')
        ->assertCount('notifications', 1)
        ->call('openNotification', $notification->id)
        ->assertSet('unreadCount', 0)
        ->call('markAllRead');

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});

function inAppNotificationMessage(int $recipientId, string $dedupeKey = 'sdi-result:1'): InAppNotificationMessage
{
    return new InAppNotificationMessage(
        recipientId: $recipientId,
        type: 'sdi.outcome.received',
        category: 'sdi',
        severity: 'success',
        title: 'Documento consegnato',
        body: 'La fattura FT-001 è stata consegnata dal Sistema di Interscambio.',
        sourceType: 'ei_outbound_log',
        sourceId: '1',
        dedupeKey: $dedupeKey,
        occurredAt: now(),
        resourceType: 'fiscal_document',
        resourceId: 1,
        action: 'open_document',
        metadata: ['document_number' => 'FT-001'],
    );
}
