<?php

use App\Models\Contact;
use App\Models\DocumentEvent;
use App\Models\FiscalDocument;
use App\Models\InAppNotification;
use App\Models\Payment;
use Database\Seeders\DemoModeSeeder;
use Illuminate\Support\Carbon;

it('seeds a complete operational dataset with current and historical scenarios', function () {
    Carbon::setTestNow('2026-10-02 10:00:00');

    $this->seed(DemoModeSeeder::class);

    expect(Contact::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(FiscalDocument::query()->where('type', 'sales')->count())->toBeGreaterThanOrEqual(24)
        ->and(FiscalDocument::query()->where('type', 'purchase')->count())->toBe(4)
        ->and(FiscalDocument::query()->where('type', 'proforma')->count())->toBe(4)
        ->and(FiscalDocument::query()->where('type', 'self_invoice')->count())->toBe(3)
        ->and(FiscalDocument::query()->where('type', 'credit_note')->count())->toBe(2)
        ->and(FiscalDocument::query()->where('payment_status', 'overdue')->count())->toBeGreaterThanOrEqual(3)
        ->and(FiscalDocument::query()->where('status', 'xml_validated')->count())->toBe(1)
        ->and(Payment::query()->count())->toBeGreaterThan(20);

    $dueSoon = FiscalDocument::query()
        ->where('number', '4')
        ->where('type', 'sales')
        ->where('fiscal_year', 2026)
        ->sole();
    expect($dueSoon->due_date?->toDateString())->toBe('2026-10-05');

    $overdue = FiscalDocument::query()
        ->where('number', '2')
        ->where('type', 'sales')
        ->where('fiscal_year', 2026)
        ->sole();

    expect(
        DocumentEvent::query()
            ->where('fiscal_document_id', $overdue->id)
            ->where('event_type', 'payment_reminder_sent')
            ->count()
    )->toBe(2)
        ->and(
            DocumentEvent::query()
                ->where('fiscal_document_id', $dueSoon->id)
                ->where('event_type', 'payment_reminder_sent')
                ->where('message', 'Scenario: upcoming. Promemoria per fattura in scadenza.')
                ->exists()
        )->toBeTrue();

    Carbon::setTestNow();
});

it('seeds read and unread notifications linked to demo documents', function () {
    Carbon::setTestNow('2026-10-02 10:00:00');

    $this->seed(DemoModeSeeder::class);

    expect(InAppNotification::query()->count())->toBe(6)
        ->and(InAppNotification::query()->whereNull('read_at')->count())->toBe(4)
        ->and(InAppNotification::query()->whereNotNull('read_at')->count())->toBe(2)
        ->and(InAppNotification::query()->where('category', 'sdi')->count())->toBe(4)
        ->and(InAppNotification::query()->where('category', 'email')->count())->toBe(2)
        ->and(InAppNotification::query()->where('severity', 'error')->count())->toBe(1);

    Carbon::setTestNow();
});

it('replaces the demo operational dataset when seeded again', function () {
    Carbon::setTestNow('2026-10-02 10:00:00');

    $this->seed(DemoModeSeeder::class);
    $firstDocumentCount = FiscalDocument::query()->count();
    $firstNotificationCount = InAppNotification::query()->count();

    $this->seed(DemoModeSeeder::class);

    expect(FiscalDocument::query()->count())->toBe($firstDocumentCount)
        ->and(InAppNotification::query()->count())->toBe($firstNotificationCount)
        ->and(Payment::query()->whereDoesntHave('fiscalDocument')->count())->toBe(0);

    Carbon::setTestNow();
});
