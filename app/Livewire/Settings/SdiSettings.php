<?php

namespace App\Livewire\Settings;

use App\Contracts\SdiProvider;
use App\Settings\CompanySettings;
use Livewire\Component;
use App\Traits\Toast;

/**
 * Fallback SDI settings page shown when no provider plugin is installed.
 * Provider plugins (e.g., plugin-fe-openapi) override the route to show
 * their own settings page.
 */
class SdiSettings extends Component
{
    use Toast;

    public string $providerName = '';

    public bool $conservationAcknowledged = false;

    public function mount(SdiProvider $provider, CompanySettings $companySettings): void
    {
        $this->providerName = $provider->name();
        $this->conservationAcknowledged = $companySettings->conservation_acknowledged ?? false;
    }

    public function acknowledgeConservation(CompanySettings $companySettings): void
    {
        $companySettings->conservation_acknowledged = true;
        $companySettings->save();

        $this->conservationAcknowledged = true;
        $this->success(__('app.conservation.acknowledged_toast'));
    }

    public function render()
    {
        return view('livewire.settings.sdi-settings');
    }
}
