<div>
    <x-header :title="__('app.settings.services.title')" separator />

    {{-- Backup section --}}
    @if($backupManagedByEnv)
        <x-alert
            :title="__('app.settings.services.backup.managed_by_env_title')"
            :description="__('app.settings.services.backup.managed_by_env_description')"
            icon="o-cloud"
            class="alert-info mb-6"
        />
    @else
        @allowed('manage-backup-settings')
        @elseallowed
            <x-alert :title="__('app.settings.services.readonly_title')" :description="__('app.settings.services.readonly_description')" icon="o-lock-closed" class="alert-warning mb-6" />
        @endallowed

        <x-form wire:submit="save">
            <x-card :title="__('app.settings.services.backup.title')" :subtitle="__('app.settings.services.backup.subtitle')" separator>
                <x-slot:actions>
                    @allowed('manage-backup-settings')
                        <x-button :label="__('app.common.save')" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                    @endallowed
                </x-slot:actions>

                <div class="grid gap-6">

                    <div>
                        <x-toggle :label="__('app.settings.services.backup.enabled')" wire:model.live="backup_enabled" :disabled="$readonly" />
                    </div>

                    @if($backup_enabled)

                        <div>
                            <p class="font-semibold text-sm mb-3">{{ __('app.settings.services.backup.schedule_section') }}</p>
                            <div class="grid lg:grid-cols-3 gap-3">
                                <x-select
                                    :label="__('app.settings.services.backup.frequency')"
                                    wire:model.live="backup_frequency"
                                    :disabled="$readonly"
                                    :options="[
                                        ['id' => 'daily',   'name' => __('app.settings.services.backup.frequency_daily')],
                                        ['id' => 'weekly',  'name' => __('app.settings.services.backup.frequency_weekly')],
                                        ['id' => 'monthly', 'name' => __('app.settings.services.backup.frequency_monthly')],
                                    ]"
                                />
                                <x-input
                                    :label="__('app.settings.services.backup.time')"
                                    wire:model="backup_time"
                                    type="time"
                                    :disabled="$readonly"
                                />

                                @if($backup_frequency === 'weekly')
                                    <x-select
                                        :label="__('app.settings.services.backup.day_of_week')"
                                        wire:model="backup_day_of_week"
                                        :disabled="$readonly"
                                        :options="[
                                            ['id' => 0, 'name' => __('app.days.sunday')],
                                            ['id' => 1, 'name' => __('app.days.monday')],
                                            ['id' => 2, 'name' => __('app.days.tuesday')],
                                            ['id' => 3, 'name' => __('app.days.wednesday')],
                                            ['id' => 4, 'name' => __('app.days.thursday')],
                                            ['id' => 5, 'name' => __('app.days.friday')],
                                            ['id' => 6, 'name' => __('app.days.saturday')],
                                        ]"
                                    />
                                @endif

                                @if($backup_frequency === 'monthly')
                                    <x-input
                                        :label="__('app.settings.services.backup.day_of_month')"
                                        wire:model="backup_day_of_month"
                                        type="number"
                                        min="1"
                                        max="28"
                                        :disabled="$readonly"
                                    />
                                @endif
                            </div>
                        </div>

                        <div>
                            <p class="font-semibold text-sm mb-3">{{ __('app.settings.services.backup.s3_section') }}</p>
                            <div class="grid lg:grid-cols-2 gap-3">
                                <x-input
                                    :label="__('app.settings.services.backup.aws_access_key_id')"
                                    wire:model="aws_access_key_id"
                                    :disabled="$readonly"
                                />
                                <x-input
                                    :label="__('app.settings.services.backup.aws_secret_access_key')"
                                    wire:model="aws_secret_access_key"
                                    type="password"
                                    :disabled="$readonly"
                                />
                                <x-input
                                    :label="__('app.settings.services.backup.aws_default_region')"
                                    wire:model="aws_default_region"
                                    placeholder="eu-central-1"
                                    :disabled="$readonly"
                                />
                                <x-input
                                    :label="__('app.settings.services.backup.aws_bucket')"
                                    wire:model="aws_bucket"
                                    :disabled="$readonly"
                                />
                                <div class="lg:col-span-2">
                                    <x-input
                                        :label="__('app.settings.services.backup.aws_endpoint')"
                                        wire:model="aws_endpoint"
                                        placeholder="https://s3.eu-central-003.backblazeb2.com"
                                        hint="{{ __('app.settings.services.backup.aws_endpoint_hint') }}"
                                        :disabled="$readonly"
                                    />
                                </div>
                                <div class="lg:col-span-2">
                                    <x-toggle
                                        :label="__('app.settings.services.backup.aws_use_path_style_endpoint')"
                                        wire:model="aws_use_path_style_endpoint"
                                        :disabled="$readonly"
                                    />
                                    <p class="text-xs text-base-content/50 mt-1">{{ __('app.settings.services.backup.aws_use_path_style_endpoint_hint') }}</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <x-button
                                    :label="__('app.settings.services.backup.test_connection')"
                                    wire:click="testConnection"
                                    icon="o-signal"
                                    class="btn-outline btn-sm"
                                    spinner="testConnection"
                                    :disabled="$readonly"
                                />
                                <p class="text-xs text-base-content/50 mt-1">{{ __('app.settings.services.backup.test_connection_hint') }}</p>
                            </div>
                        </div>

                    @endif

                </div>
            </x-card>
        </x-form>
    @endif

    {{-- Monitoring section --}}
    @if($monitoringManagedByEnv)
        <x-alert
            :title="__('app.settings.services.monitoring.managed_by_env_title')"
            :description="__('app.settings.services.monitoring.managed_by_env_description')"
            icon="o-cloud"
            class="alert-info mb-6"
        />
    @else
        @allowed('manage-monitoring-settings')
        @elseallowed
            <x-alert :title="__('app.settings.services.readonly_title')" :description="__('app.settings.services.readonly_description')" icon="o-lock-closed" class="alert-warning mb-6" />
        @endallowed

        <x-form wire:submit="saveMonitoring">
            <x-card :title="__('app.settings.services.monitoring.title')" :subtitle="__('app.settings.services.monitoring.subtitle')" separator>
                <x-slot:actions>
                    @allowed('manage-monitoring-settings')
                        <x-button :label="__('app.common.save')" type="submit" icon="o-check" class="btn-primary" spinner="saveMonitoring" />
                    @endallowed
                </x-slot:actions>

                <div class="grid gap-6">

                    <div>
                        <x-toggle :label="__('app.settings.services.monitoring.enabled')" wire:model.live="monitoring_enabled" :disabled="$monitoringReadonly" />
                    </div>

                    @if($monitoring_enabled)
                        <div class="grid lg:grid-cols-2 gap-3">
                            <div class="lg:col-span-2">
                                <x-input
                                    :label="__('app.settings.services.monitoring.dsn')"
                                    wire:model="monitoring_dsn"
                                    placeholder="https://key@sentry.example.com/1"
                                    :hint="__('app.settings.services.monitoring.dsn_hint')"
                                    :disabled="$monitoringReadonly"
                                />
                            </div>
                            <x-input
                                :label="__('app.settings.services.monitoring.environment')"
                                wire:model="monitoring_environment"
                                placeholder="production"
                                :disabled="$monitoringReadonly"
                            />
                            <x-input
                                :label="__('app.settings.services.monitoring.traces_sample_rate')"
                                wire:model="monitoring_traces_sample_rate"
                                type="number"
                                min="0"
                                max="1"
                                step="0.1"
                                :hint="__('app.settings.services.monitoring.traces_sample_rate_hint')"
                                :disabled="$monitoringReadonly"
                            />
                        </div>
                    @endif

                </div>
            </x-card>
        </x-form>
    @endif
</div>
