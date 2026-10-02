<?php

namespace App\Services;

use App\Models\User;
use LogicException;

class NotificationRecipientResolver
{
    public function resolve(): User
    {
        $users = User::query()
            ->orderByDesc('is_admin')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($users->isEmpty()) {
            throw new LogicException('Unable to resolve the in-app notification recipient because no user exists.');
        }

        return $users->first();
    }
}
