<?php

namespace App\Traits;

trait Toast
{
    public function success(string $message, string $description = '', string $position = 'toast-bottom toast-end'): void
    {
        $this->dispatch('toast', type: 'success', message: $message, description: $description, position: $position);
    }

    public function error(string $message, string $description = '', string $position = 'toast-bottom toast-end'): void
    {
        $this->dispatch('toast', type: 'error', message: $message, description: $description, position: $position);
    }

    public function warning(string $message, string $description = '', string $position = 'toast-bottom toast-end'): void
    {
        $this->dispatch('toast', type: 'warning', message: $message, description: $description, position: $position);
    }

    public function info(string $message, string $description = '', string $position = 'toast-bottom toast-end'): void
    {
        $this->dispatch('toast', type: 'info', message: $message, description: $description, position: $position);
    }
}
