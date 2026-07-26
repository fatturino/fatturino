<?php

use App\Contracts\EnvironmentCapabilities;
use App\Services\PostHogTelemetryService;
use App\Settings\BackupSettings;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public bool $enabled = false;
    public string $frequency = 'daily';
    public string $time = '02:00';
    public int $day_of_week = 1;
    public int $day_of_month = 1;
    public string $aws_access_key_id = '';
    public string $aws_secret_access_key = '';
    public string $aws_default_region = '';
    public string $aws_bucket = '';
    public string $aws_endpoint = '';
    public bool $aws_use_path_style_endpoint = false;
    public function mount(BackupSettings $settings): void { foreach (array_keys($this->rules()) as $field) $this->{$field} = $settings->{$field} ?? (is_bool($this->{$field}) ? false : ''); }
    public function save(BackupSettings $settings): void { $this->ensureAllowed(); $settings->fill($this->validate()); $settings->save(); session()->flash('success', 'Impostazioni backup salvate.'); }
    public function testConnection(): void { try { Storage::build(['driver' => 's3', 'key' => $this->aws_access_key_id, 'secret' => $this->aws_secret_access_key, 'region' => $this->aws_default_region, 'bucket' => $this->aws_bucket, 'endpoint' => $this->aws_endpoint ?: null, 'use_path_style_endpoint' => $this->aws_use_path_style_endpoint])->files('/'); app(PostHogTelemetryService::class)->capture('service_connection_tested', ['service' => 's3', 'success' => true], request()->user()); session()->flash('success', 'Connessione S3 riuscita.'); } catch (\Throwable $exception) { app(PostHogTelemetryService::class)->capture('service_connection_tested', ['service' => 's3', 'success' => false], request()->user()); $this->addError('s3', $exception->getMessage()); } }
    protected function rules(): array { $rules = ['enabled' => 'boolean', 'frequency' => 'required|in:daily,weekly,monthly', 'time' => 'required', 'day_of_week' => 'required_if:frequency,weekly|integer|between:0,6', 'day_of_month' => 'required_if:frequency,monthly|integer|between:1,28', 'aws_endpoint' => 'nullable|url', 'aws_use_path_style_endpoint' => 'boolean']; return $this->enabled ? array_merge($rules, ['aws_access_key_id' => 'required|string', 'aws_secret_access_key' => 'required|string', 'aws_default_region' => 'required|string', 'aws_bucket' => 'required|string']) : $rules; }
    public function managedByEnv(): bool { return (bool) config('backup.managed_by_env'); }
    private function ensureAllowed(): void { abort_unless(app(EnvironmentCapabilities::class)->can('manage-backup-settings'), 403, 'Operazione non consentita in questa modalità.'); }
}; ?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Servizi</h1></div></x-slot:header>
<section class="max-w-2xl"><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Backup</h2>@if(session('success'))<div class="mt-4 rounded-md bg-success-bg p-3 text-sm text-success">{{ session('success') }}</div>@endif @error('s3')<div class="mt-4 rounded-md bg-danger-bg p-3 text-sm text-danger">{{ $message }}</div>@enderror @if($this->managedByEnv())<p class="mt-4 text-sm text-content-muted">Gestito dall'infrastruttura.</p>@else<form wire:submit="save" class="mt-4 space-y-4"><label class="flex gap-2 text-sm"><input wire:model.live="enabled" type="checkbox"> Abilita backup automatico</label>@if($enabled)<div class="grid grid-cols-2 gap-4"><label class="block text-sm font-semibold">Frequenza<select wire:model.live="frequency" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="daily">Giornaliero</option><option value="weekly">Settimanale</option><option value="monthly">Mensile</option></select></label><x-settings.input wire:model="time" type="time" label="Orario"/></div>@if($frequency === 'weekly')<x-settings.input wire:model="day_of_week" type="number" label="Giorno settimana (0-6)"/>@elseif($frequency === 'monthly')<x-settings.input wire:model="day_of_month" type="number" label="Giorno mese (1-28)"/>@endif<div class="border-t pt-4"><p class="text-xs font-bold uppercase text-content-muted">Configurazione S3</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><x-settings.input wire:model="aws_access_key_id" label="Access key ID *"/><x-settings.input wire:model="aws_secret_access_key" type="password" label="Secret access key *"/><x-settings.input wire:model="aws_default_region" label="Region *"/><x-settings.input wire:model="aws_bucket" label="Bucket *"/></div><x-settings.input wire:model="aws_endpoint" class="mt-4" label="Endpoint (opzionale)"/><label class="mt-4 flex gap-2 text-sm"><input wire:model="aws_use_path_style_endpoint" type="checkbox"> Path-style endpoint</label><button wire:click="testConnection" type="button" class="mt-4 rounded-md border border-border px-4 py-2 text-sm font-semibold">Test connessione</button></div>@endif@if(app(EnvironmentCapabilities::class)->can('manage-backup-settings'))<button class="rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white">Salva backup</button>@endif</form>@endif</article></section>
