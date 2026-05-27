<?php

namespace App\Support;

use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Dispatches custom audit events (non-Eloquent actions) for Auditable models.
 * Used for email/SDI lifecycle events on Invoice and other auditable entities.
 */
class InvoiceAuditDispatcher
{
    /**
     * Dispatch a custom audit event. Silently skips when model is not Auditable,
     * so callers can pass any document type without type checks.
     *
     * @param  array<string, mixed>  $newValues
     */
    public static function dispatch(object $model, string $event, array $newValues = []): void
    {
        if (! $model instanceof Auditable) {
            return;
        }

        $model->auditEvent = $event;
        $model->isCustomEvent = true;
        $model->auditCustomOld = [];
        $model->auditCustomNew = $newValues;

        Event::dispatch(new AuditCustom($model));
    }
}
