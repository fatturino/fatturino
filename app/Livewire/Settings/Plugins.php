<?php

namespace App\Livewire\Settings;

use App\Services\PluginRegistry;
use Livewire\Component;
use App\Traits\Toast;

class Plugins extends Component
{
    use Toast;

    public function activate(string $id, PluginRegistry $registry): void
    {
        $registry->activate($id);
        $this->success(__('app.settings.plugins.activated'));
    }

    public function deactivate(string $id, PluginRegistry $registry): void
    {
        $registry->deactivate($id);
        $this->warning(__('app.settings.plugins.deactivated'));
    }

    public function render(PluginRegistry $registry)
    {
        return view('livewire.settings.plugins', [
            'plugins' => $registry->all(),
        ]);
    }
}
