<div>
    <x-header :title="__('app.settings.email.title')" separator>
        @allowed('edit-email-settings')
            <x-slot:actions>
                <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" variant="primary" spinner="save" />
            </x-slot:actions>
        @endallowed
    </x-header>

    @allowed('edit-email-settings')
    @elseallowed
        <x-alert :title="__('app.settings.email.readonly_title')" :description="__('app.settings.email.readonly_description')" icon="o-lock-closed" variant="warning" class="mb-6" />
    @endallowed

    <x-form wire:submit="save">
        @if($smtpManagedByEnv)
            <x-alert
                :title="__('app.settings.email.smtp_managed_by_env_title')"
                :description="__('app.settings.email.smtp_managed_by_env_description')"
                icon="o-cloud"
                variant="info" class="mb-6"
            />
        @else
        <div class="grid lg:grid-cols-2 gap-5">

            {{-- SMTP Configuration --}}
            <x-card :title="__('app.settings.email.smtp_section')" separator>
                <div class="grid gap-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <x-input :label="__('app.settings.email.smtp_host')" wire:model="smtp_host" :disabled="$readonly" placeholder="smtp.example.com" />
                        </div>
                        <x-input :label="__('app.settings.email.smtp_port')" wire:model="smtp_port" type="number" :disabled="$readonly" placeholder="587" />
                    </div>
                    <x-input :label="__('app.settings.email.smtp_username')" wire:model="smtp_username" :disabled="$readonly" />
                    <x-input :label="__('app.settings.email.smtp_password')" wire:model="smtp_password" type="password" :disabled="$readonly" />
                    <x-select
                        :label="__('app.settings.email.smtp_encryption')"
                        wire:model="smtp_encryption"
                        :disabled="$readonly"
                        :options="[
                            ['id' => 'tls', 'name' => 'TLS'],
                            ['id' => 'ssl', 'name' => 'SSL'],
                        ]"
                        :placeholder="__('app.settings.email.encryption_none')"
                    />
                </div>
            </x-card>

            {{-- Sender details --}}
            <x-card :title="__('app.settings.email.sender_section')" separator>
                <div class="grid gap-3">
                    <x-input :label="__('app.settings.email.from_address')" wire:model="from_address" type="email" :disabled="$readonly" placeholder="noreply@example.com" />
                    <x-input :label="__('app.settings.email.from_name')" wire:model="from_name" :disabled="$readonly" />

                    <div class="pt-2">
                        <x-button
                            :label="__('app.email.test_connection')"
                            wire:click="testConnection"
                            icon="o-paper-airplane"
                            variant="outline" size="sm"
                            spinner="testConnection"
                            :disabled="$readonly"
                        />
                        <p class="text-xs text-base-content/50 mt-1">{{ __('app.email.test_connection_hint') }}</p>
                    </div>
                </div>
            </x-card>

        </div>
        @endif

        {{-- Email templates --}}
        <div class="grid gap-5 mt-5">

            {{-- Sales invoice template --}}
            <x-card :title="__('app.settings.email.template_sales')" separator>
                <div class="grid gap-3">
                    <x-input :label="__('app.settings.email.template_subject')" wire:model="template_sales_subject" :disabled="$readonly" />
                    <x-textarea :label="__('app.settings.email.template_body')" wire:model="template_sales_body" rows="6" :disabled="$readonly" />
                    <x-checkbox :label="__('app.settings.email.auto_send')" wire:model="auto_send_sales" :disabled="$readonly" />
                    <p class="text-xs text-base-content/40">{{ __('app.email.placeholders_hint') }}</p>
                </div>
            </x-card>

            {{-- Proforma invoice template --}}
            <x-card :title="__('app.settings.email.template_proforma')" separator>
                <div class="grid gap-3">
                    <x-input :label="__('app.settings.email.template_subject')" wire:model="template_proforma_subject" :disabled="$readonly" />
                    <x-textarea :label="__('app.settings.email.template_body')" wire:model="template_proforma_body" rows="6" :disabled="$readonly" />
                    <x-checkbox :label="__('app.settings.email.auto_send')" wire:model="auto_send_proforma" :disabled="$readonly" />
                    <p class="text-xs text-base-content/40">{{ __('app.email.placeholders_hint') }}</p>
                </div>
            </x-card>

        </div>

    </x-form>
</div>
