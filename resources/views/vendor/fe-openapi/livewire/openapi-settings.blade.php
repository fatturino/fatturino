<div>
    <x-header :title="__('fe-openapi::settings.title')" separator />

    @allowed('edit-sdi-settings')
    @elseallowed
        <x-alert :title="__('fe-openapi::settings.readonly_title')" :description="__('fe-openapi::settings.readonly_description')" icon="o-lock-closed" variant="warning" class="mb-6" />
    @endallowed

    @include('partials.conservation-banner')

    <x-tabs selected="service-tab">
        {{-- Tab 1: Service status and configuration --}}
        <x-tab name="service-tab" :label="__('fe-openapi::settings.tab_service')" icon="o-signal">
            {{-- Status badge + action buttons --}}
            <div class="flex items-center gap-3 mb-6">
                @if($activated)
                    <x-badge :value="__('fe-openapi::settings.service_active')" variant="success" icon="o-check-circle" />
                    @allowed('edit-sdi-settings')
                        <x-button
                            :label="__('fe-openapi::settings.deactivate')"
                            wire:click="deactivate"
                            wire:confirm="{{ __('fe-openapi::settings.deactivate_confirm') }}"
                            icon="o-x-circle"
                            variant="danger" size="sm"
                            spinner="deactivate"
                        />
                    @endallowed
                @else
                    <x-badge :value="__('fe-openapi::settings.service_inactive')" variant="warning" icon="o-exclamation-triangle" />
                @endif
            </div>

            <x-form wire:submit="save">
                <div class="grid gap-5 max-w-xl">
                    <x-input
                        :label="__('fe-openapi::settings.api_token')"
                        wire:model="api_token"
                        type="password"
                        :hint="__('fe-openapi::settings.api_token_hint')"
                        :disabled="$activated"
                    />

                    <x-toggle
                        :label="__('fe-openapi::settings.sandbox_mode')"
                        wire:model="sandbox"
                        :hint="__('fe-openapi::settings.sandbox_hint')"
                        :disabled="$activated"
                    />

                    <x-input
                        :label="__('fe-openapi::settings.sdi_code')"
                        wire:model="company_sdi_code"
                        :hint="__('fe-openapi::settings.sdi_code_hint')"
                        :disabled="$activated"
                    />
                </div>

                @unless($activated || $readonly)
                    <x-slot:actions>
                        <x-button
                            :label="__('fe-openapi::settings.check_connection')"
                            wire:click="checkConnection"
                            icon="o-signal"
                            spinner="checkConnection"
                        />
                        <x-button
                            :label="__('app.common.save')"
                            wire:click="save"
                            icon="o-check"
                            spinner="save"
                        />
                        <x-button
                            :label="__('fe-openapi::settings.activate')"
                            wire:click="activate"
                            icon="o-check-circle"
                            variant="primary"
                            spinner="activate"
                        />
                    </x-slot:actions>
                @endunless
            </x-form>
        </x-tab>

        {{-- Tab 2: Webhook configuration --}}
        <x-tab name="webhook-tab" :label="__('fe-openapi::settings.tab_webhook')" icon="o-arrow-path">
            <div class="mb-6 max-w-xl">
                <x-input
                    :label="__('fe-openapi::settings.webhook_url')"
                    wire:model="webhook_url"
                    placeholder="https://my-tunnel.trycloudflare.com"
                    :hint="__('fe-openapi::settings.webhook_url_hint')"
                    :disabled="$readonly"
                />
            </div>

            @if($activated)
                <x-card :title="__('fe-openapi::settings.webhook_title')">
                    @if($hasWebhookSecret)
                        <div class="flex items-center gap-2 mb-3">
                            <x-badge :value="__('fe-openapi::settings.webhook_active')" variant="success" icon="o-check-circle" />
                        </div>
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">URL:</span> <code class="bg-base-200 px-1.5 py-0.5 rounded text-xs">{{ rtrim($webhook_url ?: config('app.url'), '/') }}/api/openapi/webhook</code></p>
                            <p><span class="font-medium">{{ __('fe-openapi::settings.webhook_events') }}:</span> supplier-invoice, customer-notification, customer-invoice</p>
                        </div>
                    @else
                        <div class="flex items-center gap-2 mb-3">
                            <x-badge :value="__('fe-openapi::settings.webhook_not_configured')" variant="warning" icon="o-exclamation-triangle" />
                        </div>
                    @endif

                    @allowed('edit-sdi-settings')
                        <x-button
                            :label="__('fe-openapi::settings.webhook_reconfigure')"
                            wire:click="reconfigureCallbacks"
                            icon="o-arrow-path"
                            size="sm" class="mt-3"
                            spinner="reconfigureCallbacks"
                        />
                    @endallowed
                </x-card>

                {{-- Webhook simulation (sandbox only) --}}
                @if($sandbox && !$readonly)
                    <x-card :title="__('fe-openapi::settings.simulate_title')" :subtitle="__('fe-openapi::settings.simulate_description')" class="mt-4" separator>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <x-select
                                :label="__('fe-openapi::settings.simulate_type')"
                                wire:model.live="simulationType"
                                :options="[
                                    ['id' => 'supplier-invoice', 'name' => 'Supplier Invoice'],
                                    ['id' => 'customer-notification', 'name' => 'Customer Notification'],
                                ]"
                            />

                            @if($simulationType === 'customer-notification')
                                <x-select
                                    :label="__('fe-openapi::settings.simulate_notification_type')"
                                    wire:model="simulationNotificationType"
                                    :options="[
                                        ['id' => 'NS', 'name' => 'NS - Notifica di Scarto'],
                                        ['id' => 'RC', 'name' => 'RC - Ricevuta di Consegna'],
                                        ['id' => 'MC', 'name' => 'MC - Mancata Consegna'],
                                        ['id' => 'DT', 'name' => 'DT - Decorrenza Termini'],
                                        ['id' => 'NE', 'name' => 'NE - Esito Committente'],
                                        ['id' => 'AT', 'name' => 'AT - Attestazione'],
                                        ['id' => 'EC', 'name' => 'EC - Esito Cessionario'],
                                    ]"
                                />
                            @endif

                            @if(in_array($simulationType, ['customer-notification', 'legal-storage-receipt']))
                                <x-input
                                    :label="__('fe-openapi::settings.simulate_invoice_uuid')"
                                    wire:model="simulationInvoiceUuid"
                                    placeholder="UUID fattura inviata"
                                />
                            @endif
                        </div>

                        <x-slot:actions>
                            <x-button
                                :label="__('fe-openapi::settings.simulate_send')"
                                wire:click="simulateWebhook"
                                icon="o-play"
                                variant="primary"
                                spinner="simulateWebhook"
                            />
                        </x-slot:actions>
                    </x-card>
                @endif
            @else
                <x-alert icon="o-information-circle" variant="info">
                    {{ __('fe-openapi::settings.activate_first') }}
                </x-alert>
            @endif
        </x-tab>
    </x-tabs>

    {{-- Instructions --}}
    <x-card :title="__('fe-openapi::settings.instructions_title')" class="mt-6">
        <p class="mb-3 text-sm">{{ __('fe-openapi::settings.instructions_intro') }}</p>
        <ol class="list-decimal list-inside space-y-1 text-sm">
            <li>Registrati su <a href="https://openapi.it" target="_blank" rel="external noopener" class="text-primary hover:underline font-medium">OpenAPI.it</a></li>
            <li>{{ __('fe-openapi::settings.instructions_step_2') }}</li>
            <li>{{ __('fe-openapi::settings.instructions_step_3') }}</li>
        </ol>
    </x-card>
</div>
