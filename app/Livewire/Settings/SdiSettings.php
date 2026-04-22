<?php

namespace App\Livewire\Settings;

use App\Contracts\SdiProvider;
use Livewire\Component;

/**
 * Fallback SDI settings page shown when no provider plugin is installed.
 * Provider plugins (e.g., plugin-fe-openapi) override the route to show
 * their own settings page.
 */
class SdiSettings extends Component
{
    public string $providerName = '';

    public function mount(SdiProvider $provider): void
    {
        $this->providerName = $provider->name();
    }

    public function render()
    {
        return view('livewire.settings.sdi-settings');
    }
}
