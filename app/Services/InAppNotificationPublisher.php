<?php

namespace App\Services;

use App\Models\InAppNotification;
use Illuminate\Database\QueryException;

class InAppNotificationPublisher
{
    public function publish(InAppNotificationMessage $message): InAppNotification
    {
        try {
            return InAppNotification::query()->firstOrCreate(
                [
                    'user_id' => $message->recipientId,
                    'dedupe_key' => $message->dedupeKey,
                ],
                [
                    'type' => $message->type,
                    'category' => $message->category,
                    'severity' => $message->severity,
                    'title' => $message->title,
                    'body' => $message->body,
                    'resource_type' => $message->resourceType,
                    'resource_id' => $message->resourceId,
                    'action' => $message->action,
                    'source_type' => $message->sourceType,
                    'source_id' => $message->sourceId,
                    'metadata' => $message->metadata,
                    'occurred_at' => $message->occurredAt,
                    'expires_at' => $message->expiresAt,
                ],
            );
        } catch (QueryException $exception) {
            $notification = InAppNotification::query()
                ->where('user_id', $message->recipientId)
                ->where('dedupe_key', $message->dedupeKey)
                ->first();

            if ($notification !== null) {
                return $notification;
            }

            throw $exception;
        }
    }
}
