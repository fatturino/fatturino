<?php

use App\Contracts\EnvironmentCapabilities;
use App\Enums\AtecoCode;
use App\Enums\FiscalRegime;
use App\Rules\ItalianVatNumber;
use App\Settings\CompanySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component {
    use WithFileUploads;

    public string $company_name = '';

    public string $company_vat_number = '';

    public string $company_tax_code = '';

    public string $company_address = '';

    public string $company_postal_code = '';

    public string $company_city = '';

    public string $company_province = '';

    public string $company_country = 'IT';

    public string $company_email = '';

    public string $company_pec = '';

    public string $company_sdi_code = '';

    public string $company_fiscal_regime = 'RF01';

    public bool $rf19_self_invoices_enabled = false;

    public array $company_ateco_codes = [];

    public ?UploadedFile $company_logo = null;

    public bool $remove_logo = false;

    public ?string $companyLogoPath = null;

    public function mount(CompanySettings $settings): void
    {
        foreach (array_keys($this->rules()) as $field) {
            if (! str_ends_with($field, '.*') && $field !== 'company_logo' && $field !== 'remove_logo') {
                $this->{$field} = $settings->{$field} ?? (is_array($this->{$field}) ? [] : '');
            }
        }
        $this->companyLogoPath = $settings->company_logo_path;
    }

    public function save(CompanySettings $settings): void
    {
        $this->ensureAllowed();
        $this->company_ateco_codes = collect($this->company_ateco_codes)
            ->filter(fn (mixed $code) => is_string($code) && trim($code) !== '')
            ->map(fn (string $code) => trim($code))
            ->unique()
            ->values()
            ->all();
        $validated = $this->validate();
        $previousLogoPath = $settings->company_logo_path;
        if ($this->company_logo) {
            $settings->company_logo_path = $this->company_logo->store('logos', 'public');
        } elseif ($this->remove_logo) {
            $settings->company_logo_path = null;
        }
        $oldRegime = $settings->company_fiscal_regime;
        $oldRf19 = $settings->rf19_self_invoices_enabled;
        unset($validated['company_logo'], $validated['remove_logo']);
        $validated['company_vat_number'] = ItalianVatNumber::normalize($validated['company_vat_number'] ?: null) ?? '';
        $validated['company_ateco_codes'] = $this->company_ateco_codes;
        $settings->fill($validated);
        $settings->save();
        if ($previousLogoPath && $previousLogoPath !== $settings->company_logo_path) {
            Storage::disk('public')->delete($previousLogoPath);
        }
        $this->companyLogoPath = $settings->company_logo_path;
        $this->company_logo = null;
        $this->remove_logo = false;
        if ($oldRegime !== $settings->company_fiscal_regime || $oldRf19 !== $settings->rf19_self_invoices_enabled) {
            Log::info('Fiscal regime settings updated', ['user_id' => request()->user()?->id, 'old_regime' => $oldRegime, 'new_regime' => $settings->company_fiscal_regime, 'old_rf19_self_invoices_enabled' => $oldRf19, 'new_rf19_self_invoices_enabled' => $settings->rf19_self_invoices_enabled]);
        }
        session()->flash('success', 'Impostazioni salvate.');
    }

    protected function rules(): array
    {
        return ['company_name' => 'required|string', 'company_vat_number' => ['nullable', new ItalianVatNumber], 'company_tax_code' => 'nullable|string', 'company_address' => 'nullable|string', 'company_postal_code' => 'nullable|string', 'company_city' => 'nullable|string', 'company_province' => 'nullable|string', 'company_country' => 'required|size:2', 'company_email' => 'nullable|email', 'company_pec' => 'nullable|string', 'company_sdi_code' => 'nullable|string', 'company_fiscal_regime' => ['required', Rule::in(array_column(FiscalRegime::options(), 'value'))], 'rf19_self_invoices_enabled' => 'boolean', 'company_ateco_codes' => 'nullable|array', 'company_ateco_codes.*' => [Rule::in(array_column(AtecoCode::options(), 'id'))], 'company_logo' => 'nullable|image|max:1024', 'remove_logo' => 'boolean'];
    }

    public function fiscalRegimes(): array
    {
        return FiscalRegime::options();
    }

    public function countries(): array
    {
        return [['value' => 'IT', 'label' => 'Italia'], ['value' => 'AT', 'label' => 'Austria'], ['value' => 'BE', 'label' => 'Belgio'], ['value' => 'BG', 'label' => 'Bulgaria'], ['value' => 'CY', 'label' => 'Cipro'], ['value' => 'HR', 'label' => 'Croazia'], ['value' => 'DK', 'label' => 'Danimarca'], ['value' => 'EE', 'label' => 'Estonia'], ['value' => 'FI', 'label' => 'Finlandia'], ['value' => 'FR', 'label' => 'Francia'], ['value' => 'DE', 'label' => 'Germania'], ['value' => 'GR', 'label' => 'Grecia'], ['value' => 'IE', 'label' => 'Irlanda'], ['value' => 'LV', 'label' => 'Lettonia'], ['value' => 'LT', 'label' => 'Lituania'], ['value' => 'LU', 'label' => 'Lussemburgo'], ['value' => 'MT', 'label' => 'Malta'], ['value' => 'NL', 'label' => 'Paesi Bassi'], ['value' => 'PL', 'label' => 'Polonia'], ['value' => 'PT', 'label' => 'Portogallo'], ['value' => 'CZ', 'label' => 'Repubblica Ceca'], ['value' => 'RO', 'label' => 'Romania'], ['value' => 'SK', 'label' => 'Slovacchia'], ['value' => 'SI', 'label' => 'Slovenia'], ['value' => 'ES', 'label' => 'Spagna'], ['value' => 'SE', 'label' => 'Svezia'], ['value' => 'HU', 'label' => 'Ungheria'], ['value' => 'CH', 'label' => 'Svizzera'], ['value' => 'GB', 'label' => 'Regno Unito'], ['value' => 'US', 'label' => 'Stati Uniti'], ['value' => 'CN', 'label' => 'Cina']];
    }

    public function atecoLabel(string $code): string
    {
        return AtecoCode::label($code);
    }

    private function ensureAllowed(): void
    {
        abort_unless(app(EnvironmentCapabilities::class)->can('edit-company-settings'), 403, 'Operazione non consentita in questa modalità.');
    }
};
?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Configurazione</p><h1 class="text-lg font-bold text-content">Dati azienda</h1></div></x-slot:header>
<section class="space-y-6">
    <form wire:submit="save" class="grid max-w-6xl gap-6 lg:grid-cols-2">
        @if(session('success'))<div class="lg:col-span-2 rounded-lg border border-success/20 bg-success-bg p-4 text-sm font-medium text-success" role="status">{{ session('success') }}</div>@endif

        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6"><h2 class="text-base font-bold text-content">Informazioni generali</h2><div class="mt-5 space-y-4"><x-settings.input wire:model="company_name" label="Nome azienda *" autocomplete="organization"/><x-settings.input wire:model="company_vat_number" label="Partita IVA"/><x-settings.input wire:model="company_tax_code" label="Codice fiscale"/></div></article>
        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6"><h2 class="text-base font-bold text-content">Indirizzo</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><x-settings.input wire:model="company_address" label="Via" autocomplete="street-address"/></div><x-settings.input wire:model="company_postal_code" label="CAP" autocomplete="postal-code"/><x-settings.input wire:model="company_city" label="Città" autocomplete="address-level2"/><x-settings.input wire:model="company_province" label="Provincia" maxlength="2"/><label class="block text-sm font-medium text-content">Paese *<x-select wire:model="company_country" :options="$this->countries()" option-value="value" option-label="label" /></label></div></article>
        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6"><h2 class="text-base font-bold text-content">Fatturazione elettronica</h2><div class="mt-5 space-y-4"><label class="block text-sm font-medium text-content">Regime fiscale *<x-select wire:model.live="company_fiscal_regime" :options="$this->fiscalRegimes()" option-value="value" option-label="label" /></label><x-settings.input wire:model="company_email" type="email" label="Email" autocomplete="email"/><x-settings.input wire:model="company_pec" label="PEC"/><x-settings.input wire:model="company_sdi_code" label="Codice SDI" maxlength="7"/>@if($company_fiscal_regime === 'RF19')<x-toggle wire:model="rf19_self_invoices_enabled" label="Abilita autofatture RF19" hint="Solo per operazioni con l’estero." />@endif</div></article>

        <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)] sm:p-6"><h2 class="text-base font-bold text-content">Codici ATECO e logo</h2><div class="mt-5 space-y-6">
            <div x-data="{
                selected: @entangle('company_ateco_codes'), query: '', results: [], loading: false, error: '', open: false, activeIndex: -1, timer: null, controller: null,
                labels: {{ Js::from(collect($company_ateco_codes)->mapWithKeys(fn ($code) => [$code => $this->atecoLabel($code)])) }},
                get selectedEntries() { return this.selected.map(code => ({ code, label: this.labels[code] || code })); },
                async search() { clearTimeout(this.timer); this.error = ''; if (this.query.trim().length < 2) { this.results = []; this.open = this.query.length > 0; return; } this.timer = setTimeout(async () => { this.controller?.abort(); this.controller = new AbortController(); this.loading = true; this.open = true; try { const response = await fetch('/api/v1/ateco/search?q=' + encodeURIComponent(this.query.trim()), { credentials: 'same-origin', signal: this.controller.signal, headers: { Accept: 'application/json' } }); if (!response.ok) throw new Error(); this.results = await response.json(); this.activeIndex = this.results.length ? 0 : -1; } catch (exception) { if (exception.name !== 'AbortError') { this.error = 'La ricerca ATECO non è disponibile. Riprova.'; this.results = []; } } finally { this.loading = false; } }, 250); },
                choose(item) { if (!this.selected.includes(item.code)) this.selected.push(item.code); this.labels[item.code] = item.code + ' - ' + item.description; this.query = ''; this.results = []; this.open = false; this.$nextTick(() => this.$refs.search.focus()); }, remove(code) { this.selected = this.selected.filter(value => value !== code); }, move(amount) { if (!this.results.length) return; this.activeIndex = (this.activeIndex + amount + this.results.length) % this.results.length; this.$nextTick(() => document.getElementById('ateco-option-' + this.activeIndex)?.scrollIntoView({ block: 'nearest' })); }
            }" class="space-y-2">
                <label for="ateco-search" class="block text-sm font-medium text-content">Codici ATECO</label>
                <p id="ateco-help" class="text-xs leading-5 text-content-muted">Cerca per codice o descrizione, quindi aggiungi uno o più codici.</p>
                <div class="relative">
                    <input x-ref="search" id="ateco-search" x-model="query" x-on:input="search()" x-on:focus="open = true" x-on:keydown.escape="open = false" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="if (activeIndex >= 0) choose(results[activeIndex])" type="search" autocomplete="off" role="combobox" :aria-expanded="open" aria-controls="ateco-results" aria-describedby="ateco-help" placeholder="Es. 62 o sviluppo software" class="block h-11 w-full rounded-lg border border-border-strong bg-white px-3 text-sm text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <div x-show="loading" class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-primary" role="status"><x-icon name="o-arrow-path" class="size-4 animate-spin" /><span class="sr-only">Ricerca in corso</span></div>
                    <ul id="ateco-results" x-show="open && (query.length >= 2 || loading || error)" x-cloak x-on:click.outside="open = false" class="absolute z-20 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-border bg-white p-1 shadow-[var(--shadow-elevated)]" role="listbox">
                        <template x-for="(item, index) in results" :key="item.code"><li :id="'ateco-option-' + index" role="option" :aria-selected="activeIndex === index" x-on:mouseenter="activeIndex = index" x-on:click="choose(item)" class="cursor-pointer rounded-lg px-3 py-2 text-sm text-content hover:bg-primary-subtle" :class="{ 'bg-primary-subtle': activeIndex === index }"><span class="font-semibold" x-text="item.code"></span><span class="ml-1 text-content-muted" x-text="item.description"></span></li></template>
                        <li x-show="!loading && !error && query.length >= 2 && !results.length" class="px-3 py-2 text-sm text-content-muted">Nessun codice trovato.</li><li x-show="error" class="px-3 py-2 text-sm text-danger" role="alert" x-text="error"></li>
                    </ul>
                </div>
                <div x-show="selectedEntries.length" x-cloak class="flex flex-wrap gap-2 pt-1" aria-live="polite"><template x-for="item in selectedEntries" :key="item.code"><span class="inline-flex max-w-full items-center gap-2 rounded-lg bg-primary-subtle py-1.5 pl-2.5 pr-1.5 text-xs font-medium text-content"><span class="truncate" x-text="item.label"></span><button type="button" x-on:click="remove(item.code)" class="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-content-muted transition hover:bg-white hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20" :aria-label="'Rimuovi ' + item.label">×</button></span></template></div>
            </div>

            <div x-data="{ dragging: false, uploading: false, progress: 0, fileName: '', fileSize: '', preview: null, localError: '', setFile(file) { this.localError = ''; if (!file) return; if (!file.type.startsWith('image/')) { this.localError = 'Seleziona un file immagine.'; this.$refs.input.value = ''; return; } if (file.size > 1024 * 1024) { this.localError = 'Il logo non può superare 1 MB.'; this.$refs.input.value = ''; return; } this.fileName = file.name; this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB'; this.preview = URL.createObjectURL(file); }, clear() { this.$refs.input.value = ''; this.fileName = ''; this.fileSize = ''; this.preview = null; this.localError = ''; $wire.set('company_logo', null); } }" x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="dragging = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles: true }))" x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-progress="progress = $event.detail.progress" x-on:livewire-upload-finish="uploading = false; progress = 100" x-on:livewire-upload-error="uploading = false" class="space-y-3">
                <div class="flex items-baseline justify-between gap-3"><label for="company-logo" class="text-sm font-medium text-content">Logo azienda</label><span class="text-xs text-content-muted">PNG, JPG, GIF o WebP, max 1 MB</span></div>
                <div :class="dragging ? 'border-primary bg-primary-subtle' : 'border-border bg-surface-muted/50'" class="rounded-xl border-2 border-dashed p-4 transition-colors sm:p-5"><input x-ref="input" id="company-logo" wire:model="company_logo" x-on:change="setFile($event.target.files[0])" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="sr-only"><div class="flex flex-col gap-4 sm:flex-row sm:items-center"><div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-primary-subtle text-primary"><x-icon name="o-arrow-up-tray" class="size-5" /></div><div class="min-w-0 flex-1"><p class="text-sm font-medium text-content">Trascina qui un’immagine o scegli un file</p><p class="mt-1 text-xs text-content-muted">Il logo verrà mostrato nei documenti aziendali.</p></div><label for="company-logo" class="inline-flex h-11 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus-within:ring-2 focus-within:ring-primary/20">Scegli file</label></div></div>
                <p x-show="localError" x-cloak class="text-sm text-danger" role="alert" x-text="localError"></p>@error('company_logo')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                <div x-show="fileName" x-cloak class="flex items-center gap-3 rounded-lg bg-surface-muted p-3" aria-live="polite"><img x-show="preview" :src="preview" alt="Anteprima del nuovo logo" class="size-10 rounded border border-border bg-white object-contain p-1"><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-content" x-text="fileName"></p><p class="text-xs text-content-muted" x-text="fileSize"></p></div><button type="button" x-on:click="clear()" class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-content transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20">Annulla</button></div>
                <div x-show="uploading" x-cloak role="status" aria-live="polite"><div class="flex justify-between text-xs font-medium text-primary"><span>Caricamento in corso</span><span x-text="progress + '%'"></span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-primary/15"><div class="h-full bg-primary transition-all" :style="`width: ${progress}%`"></div></div></div>
                @if($companyLogoPath && ! $remove_logo)<div class="flex flex-col gap-3 rounded-lg border border-border-light p-3 sm:flex-row sm:items-center"><img src="{{ asset('storage/'.$companyLogoPath) }}" alt="Logo azienda attuale" class="size-12 rounded border border-border bg-white object-contain p-1"><span class="flex-1 text-sm text-content-muted">Logo attuale</span><x-toggle wire:model="remove_logo" label="Rimuovi logo" hint="La rimozione verrà applicata al salvataggio." /></div>@endif
            </div>
        </div></article>

        <div class="flex flex-col-reverse gap-3 border-t border-border-light pt-5 sm:flex-row sm:items-center lg:col-span-2">@if(app(EnvironmentCapabilities::class)->can('edit-company-settings'))<button wire:loading.attr="disabled" wire:target="save,company_logo" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-bold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60" type="submit"><span wire:loading.remove wire:target="save">Salva impostazioni</span><span wire:loading wire:target="save" role="status">Salvataggio in corso...</span></button>@else<p class="text-sm text-content-muted">Configurazione in sola lettura.</p>@endif</div>
    </form>
</section>
