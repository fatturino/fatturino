<?php

namespace App\Livewire\Settings;

use App\Contracts\EnvironmentCapabilities;
use App\Enums\Capability;
use App\Settings\BackupSettings;
use App\Settings\MonitoringSettings;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use App\Traits\Toast;

class Services extends Component
{
    use Toast;

    // Backup settings
    public bool $backup_enabled = false;

    public string $backup_frequency = 'daily';

    public string $backup_time = '02:00';

    public int $backup_day_of_week = 1;

    public int $backup_day_of_month = 1;

    // S3 credentials
    public ?string $aws_access_key_id = null;

    public ?string $aws_secret_access_key = null;

    public ?string $aws_default_region = null;

    public ?string $aws_bucket = null;

    public ?string $aws_endpoint = null;

    public bool $aws_use_path_style_endpoint = false;

    // Monitoring settings
    public bool $monitoring_enabled = false;

    public ?string $monitoring_dsn = null;

    public string $monitoring_environment = 'production';

    public float $monitoring_traces_sample_rate = 0.0;

    // UI state
    public bool $readonly = false;

    public bool $backupManagedByEnv = false;

    public bool $monitoringManagedByEnv = false;

    public bool $monitoringReadonly = false;

    public function mount(BackupSettings $settings, MonitoringSettings $monitoring, EnvironmentCapabilities $capabilities): void
    {
        $this->readonly = $capabilities->cannot(Capability::ManageBackupSettings);
        $this->monitoringReadonly = $capabilities->cannot(Capability::ManageMonitoringSettings);
        $this->backupManagedByEnv = (bool) config('backup.managed_by_env');
        $this->monitoringManagedByEnv = (bool) config('monitoring.managed_by_env');

        $this->monitoring_enabled = $monitoring->enabled;
        $this->monitoring_dsn = $monitoring->dsn;
        $this->monitoring_environment = $monitoring->environment;
        $this->monitoring_traces_sample_rate = $monitoring->traces_sample_rate;

        $this->backup_enabled = $settings->enabled;
        $this->backup_frequency = $settings->frequency;
        $this->backup_time = $settings->time;
        $this->backup_day_of_week = $settings->day_of_week;
        $this->backup_day_of_month = $settings->day_of_month;
        $this->aws_access_key_id = $settings->aws_access_key_id;
        $this->aws_secret_access_key = $settings->aws_secret_access_key;
        $this->aws_default_region = $settings->aws_default_region;
        $this->aws_bucket = $settings->aws_bucket;
        $this->aws_endpoint = $settings->aws_endpoint;
        $this->aws_use_path_style_endpoint = $settings->aws_use_path_style_endpoint;
    }

    public function save(BackupSettings $settings, EnvironmentCapabilities $capabilities): void
    {
        if ($capabilities->cannot(Capability::ManageBackupSettings)) {
            $this->error(__('app.settings.services.readonly_error'));

            return;
        }

        $rules = [
            'backup_frequency' => 'required|in:daily,weekly,monthly',
            'backup_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'backup_day_of_week' => 'required_if:backup_frequency,weekly|integer|between:0,6',
            'backup_day_of_month' => 'required_if:backup_frequency,monthly|integer|between:1,28',
            'aws_endpoint' => 'nullable|url',
            'aws_use_path_style_endpoint' => 'boolean',
        ];

        if ($this->backup_enabled) {
            $rules['aws_access_key_id'] = 'required|string';
            $rules['aws_secret_access_key'] = 'required|string';
            $rules['aws_default_region'] = 'required|string';
            $rules['aws_bucket'] = 'required|string';
        }

        $this->validate($rules);

        $settings->enabled = $this->backup_enabled;
        $settings->frequency = $this->backup_frequency;
        $settings->time = $this->backup_time;
        $settings->day_of_week = $this->backup_day_of_week;
        $settings->day_of_month = $this->backup_day_of_month;
        $settings->aws_access_key_id = $this->aws_access_key_id ?: null;
        $settings->aws_secret_access_key = $this->aws_secret_access_key ?: null;
        $settings->aws_default_region = $this->aws_default_region ?: null;
        $settings->aws_bucket = $this->aws_bucket ?: null;
        $settings->aws_endpoint = $this->aws_endpoint ?: null;
        $settings->aws_use_path_style_endpoint = $this->aws_use_path_style_endpoint;

        $settings->save();

        $this->success(__('app.settings.services.backup.saved'));
    }

    public function saveMonitoring(MonitoringSettings $monitoring, EnvironmentCapabilities $capabilities): void
    {
        if ($capabilities->cannot(Capability::ManageMonitoringSettings)) {
            $this->error(__('app.settings.services.readonly_error'));

            return;
        }

        $rules = [
            'monitoring_environment' => 'required|string|max:50',
            'monitoring_traces_sample_rate' => 'required|numeric|min:0|max:1',
        ];

        if ($this->monitoring_enabled) {
            $rules['monitoring_dsn'] = 'required|url';
        }

        $this->validate($rules);

        $monitoring->enabled = $this->monitoring_enabled;
        $monitoring->dsn = $this->monitoring_dsn ?: null;
        $monitoring->environment = $this->monitoring_environment;
        $monitoring->traces_sample_rate = $this->monitoring_traces_sample_rate;

        $monitoring->save();

        $this->success(__('app.settings.services.monitoring.saved'));
    }

    public function testConnection(): void
    {
        try {
            $disk = Storage::build([
                'driver' => 's3',
                'key' => $this->aws_access_key_id,
                'secret' => $this->aws_secret_access_key,
                'region' => $this->aws_default_region ?? '',
                'bucket' => $this->aws_bucket ?? '',
                'endpoint' => $this->aws_endpoint ?: null,
                'use_path_style_endpoint' => $this->aws_use_path_style_endpoint,
            ]);

            // Listing root files is a lightweight operation that verifies credentials
            $disk->files('/');

            $this->success(__('app.settings.services.backup.connection_success'));
        } catch (\Throwable $e) {
            $this->error(__('app.settings.services.backup.connection_error'));
        }
    }

    public function render()
    {
        return view('livewire.settings.services');
    }
}
