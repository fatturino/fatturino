<div>
    <x-header :title="__('app.settings.plugins.title')" :subtitle="__('app.settings.plugins.subtitle')" separator />

    @if(count($plugins) === 0)
        <x-card>
            <div class="flex flex-col items-center justify-center py-12 text-base-content/50">
                <x-icon name="o-puzzle-piece" class="w-16 h-16 mb-4" />
                <p class="text-lg font-medium">{{ __('app.settings.plugins.empty') }}</p>
            </div>
        </x-card>
    @else
        <x-alert icon="o-information-circle" variant="info" class="mb-4">
            {{ __('app.settings.plugins.restart_hint') }}
        </x-alert>

        <div class="grid gap-4">
            @foreach($plugins as $id => $plugin)
                <x-card>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="font-bold text-lg">{{ $plugin['name'] }}</h3>
                                <x-badge :value="$plugin['version']" variant="neutral" type="outline" />
                                @if($plugin['active'])
                                    <x-badge :value="__('app.settings.plugins.active')" variant="success" type="soft" />
                                @else
                                    <x-badge :value="__('app.settings.plugins.inactive')" variant="warning" type="soft" />
                                @endif
                            </div>
                            @if($plugin['description'])
                                <p class="text-base-content/60 mt-1">{{ $plugin['description'] }}</p>
                            @endif
                            @if($plugin['author'])
                                <p class="text-base-content/40 text-sm mt-1">{{ __('app.settings.plugins.author') }}: {{ $plugin['author'] }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if($plugin['locked'])
                                <x-badge :value="__('app.settings.plugins.locked')" variant="neutral" type="outline" icon="o-lock-closed" />
                            @elseif($plugin['active'])
                                <x-button
                                    :label="__('app.settings.plugins.deactivate')"
                                    wire:click="deactivate('{{ $id }}')"
                                    wire:confirm="{{ __('app.settings.plugins.deactivate_confirm', ['name' => $plugin['name']]) }}"
                                    icon="o-x-circle"
                                    variant="danger" size="sm"
                                    spinner="deactivate('{{ $id }}')"
                                />
                            @else
                                <x-button
                                    :label="__('app.settings.plugins.activate')"
                                    wire:click="activate('{{ $id }}')"
                                    icon="o-check-circle"
                                    variant="success" size="sm"
                                    spinner="activate('{{ $id }}')"
                                />
                            @endif
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
