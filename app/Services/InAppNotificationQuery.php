<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class InAppNotificationQuery
{
    /**
     * @param  array{filter?: string, category?: string, severity?: string}  $filters
     * @return CursorPaginator<int, InAppNotification>
     */
    public function paginateFor(User $user, array $filters = [], int $perPage = 20): CursorPaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return $this->filtered($user, $filters)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * @param  array{filter?: string, category?: string, severity?: string}  $filters
     */
    public function unreadCountFor(User $user, array $filters = []): int
    {
        return $this->filtered($user, $filters)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @param  array{filter?: string, category?: string, severity?: string}  $filters
     * @return Builder<InAppNotification>
     */
    private function filtered(User $user, array $filters): Builder
    {
        return InAppNotification::query()
            ->whereBelongsTo($user)
            ->when(($filters['filter'] ?? null) === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(filled($filters['severity'] ?? null), fn (Builder $query) => $query->where('severity', $filters['severity']));
    }
}
