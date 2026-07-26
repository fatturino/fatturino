<?php

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public bool $sandbox;

    public bool $activated;

    public bool $managedByEnv;

    public bool $demoMode;

    public bool $conservationAcknowledged;

    public bool $hasWebhookSecret;

    public string $webhookCallbackUrl;

    public string $companySdiCode;

    public function mount(OpenApiSettings $settings, CompanySettings $company): void
    {
        $this->sandbox = $settings->sandbox;
        $this->activated = $settings->activated;
        $this->managedByEnv = (bool) config('fe-openapi.managed_by_env');
        $this->demoMode = (bool) config('demo.enabled');
        $this->conservationAcknowledged = $company->conservation_acknowledged ?? false;
        $this->hasWebhookSecret = filled($settings->webhook_secret);
        $baseUrl = filled($settings->webhook_url) ? rtrim($settings->webhook_url, '/') : rtrim(config('app.url'), '/');
        $this->webhookCallbackUrl = $baseUrl.'/api/v1/openapi/webhook';
        $this->companySdiCode = $settings->company_sdi_code;
    }

    public function codiceDestinatario(): string
    {
        return OpenApiSdiService::CODICE_DESTINATARIO;
    }
};
?>
<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Fatturazione elettronica</h1></div></x-slot:header>
<section class="space-y-6" x-data="openApiSettings({ sandbox: @js($sandbox), activated: @js($activated), managed: @js($managedByEnv), demo: @js($demoMode), csrf: @js(csrf_token()) })"><div x-show="message" x-text="message" class="rounded-md border p-4 text-sm" :class="success ? 'border-success/20 bg-success-bg text-success' : 'border-danger/20 bg-danger-bg text-danger'"></div><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><div class="flex items-center justify-between"><div><h2 class="font-bold">Servizio OpenAPI</h2><p class="mt-1 text-sm text-content-muted">Codice destinatario: <strong>{{ $this->codiceDestinatario() }}</strong></p></div><span class="rounded-full px-3 py-1 text-xs font-bold" :class="(demo || activated) ? 'bg-success-bg text-success' : 'bg-surface-muted text-content-muted'" x-text="(demo || activated) ? 'Attivo' : 'Non attivo'"></span></div>@if($managedByEnv || $demoMode)<p class="mt-4 text-sm text-content-muted">{{ $demoMode ? 'Modalità demo: servizio sempre attivo.' : 'Configurazione gestita dalle variabili d’ambiente.' }}</p>@else<div class="mt-4 grid gap-4 md:grid-cols-2"><label class="block text-sm font-semibold">API token<input x-model="apiToken" :disabled="activated" type="password" class="mt-2 block w-full rounded-md border border-border px-3 py-2"></label><label class="flex items-center gap-2 self-end text-sm"><input x-model="sandbox" :disabled="activated" type="checkbox"> Modalità sandbox</label><label class="block text-sm font-semibold">Codice SDI<input x-model="companySdiCode" :disabled="activated" value="{{ $companySdiCode }}" class="mt-2 block w-full rounded-md border border-border px-3 py-2"></label><label class="block text-sm font-semibold">URL webhook base<input x-model="webhookUrl" :disabled="activated" class="mt-2 block w-full rounded-md border border-border px-3 py-2" placeholder="https://mio-dominio.it"></label></div>@endif<div class="mt-5 flex flex-wrap gap-2"><button @click="send('/api/v1/openapi/check-connection', 'Connessione verificata.')" class="rounded-md border border-border px-4 py-2 text-sm font-semibold">Verifica connessione</button><template x-if="!activated && !managed && !demo"><button @click="send('/api/v1/openapi/save', 'Impostazioni salvate.')" class="rounded-md border border-border px-4 py-2 text-sm font-semibold">Salva</button></template><template x-if="!activated && !demo"><button @click="send('/api/v1/openapi/activate', 'Servizio attivato.')" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">Attiva</button></template><template x-if="activated && !demo"><button @click="send('/api/v1/openapi/deactivate', 'Servizio disattivato.')" class="rounded-md bg-danger px-4 py-2 text-sm font-bold text-white">Disattiva</button></template></div></article><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Webhook</h2><p class="mt-2 text-sm text-content-muted">Callback: <code>{{ $webhookCallbackUrl }}</code></p><p class="mt-2 text-sm" :class="{{ $hasWebhookSecret ? 'true' : 'false' }} ? 'text-success' : 'text-content-muted'">{{ $hasWebhookSecret ? 'Webhook configurato.' : 'Webhook non configurato.' }}</p>@if($sandbox)<div class="mt-4 border-t pt-4"><p class="text-sm font-semibold">Simulazione sandbox</p><div class="mt-3 flex flex-wrap gap-2"><select x-model="simulationType" class="rounded-md border border-border px-3 py-2 text-sm"><option value="supplier-invoice">Supplier invoice</option><option value="customer-notification">Customer notification</option><option value="customer-invoice">Customer invoice</option></select><input x-model="invoiceUuid" class="rounded-md border border-border px-3 py-2 text-sm" placeholder="Invoice UUID (opzionale)"><button @click="simulate()" class="rounded-md border border-border px-4 py-2 text-sm font-semibold">Invia simulazione</button></div></div>@endif</article><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Conservazione digitale</h2>@if($conservationAcknowledged)<p class="mt-2 text-sm text-success">Obbligo di conservazione preso in carico.</p>@else<p class="mt-2 text-sm text-content-muted">Le fatture elettroniche devono essere conservate per dieci anni.</p><div class="mt-4 flex gap-2"><a class="rounded-md border border-border px-4 py-2 text-sm font-semibold" target="_blank" href="https://ivaservizi.agenziaentrate.gov.it">Apri Agenzia Entrate</a><button @click="acknowledge()" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">Ho preso visione</button></div>@endif</article></section>
@script<script>Alpine.data('openApiSettings', ({ sandbox, activated, managed, demo, csrf }) => ({ sandbox, activated, managed, demo, csrf, apiToken: '', companySdiCode: '', webhookUrl: '', simulationType: 'supplier-invoice', invoiceUuid: '', message: '', success: true, async request(url, payload = {}) { const response = await fetch(url, { method: 'POST', headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf}, body: JSON.stringify(payload) }); return response.json(); }, async send(url, ok) { const data = await this.request(url, {api_token:this.apiToken, sandbox:this.sandbox, company_sdi_code:this.companySdiCode, webhook_url:this.webhookUrl}); this.success=!!data.success; this.message=data.success ? ok : (data.error || data.message || 'Errore'); if (data.activated !== undefined) this.activated=data.activated; }, async simulate() { const data = await this.request('/api/v1/openapi/simulate-webhook', {type:this.simulationType, invoice_uuid:this.invoiceUuid}); this.success=!!data.success; this.message=data.success ? 'Webhook simulato inviato.' : (data.error || 'Errore'); }, async acknowledge() { const data = await this.request('/api/v1/openapi/acknowledge-conservation'); this.success=!!data.success; this.message=data.message || 'Aggiornato.'; if (data.success) window.location.reload(); } }))</script>@endscript
