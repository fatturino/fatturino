<?php

use App\Models\Contact;
use App\Rules\ItalianTaxCode;
use App\Rules\ItalianVatNumber;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $vat_number = '';

    public string $tax_code = '';

    public string $sdi_code = '';

    public string $pec = '';

    public string $country = 'IT';

    public string $address = '';

    public string $postal_code = '';

    public string $city = '';

    public string $province = '';

    public function save(): mixed
    {
        Contact::create($this->validate());
        session()->flash('success', 'Contatto creato.');

        return $this->redirectRoute('contacts.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'nullable|email',
            'vat_number' => $this->country === 'IT' ? ['nullable', new ItalianVatNumber] : 'nullable',
            'tax_code' => ['nullable', new ItalianTaxCode],
            'sdi_code' => 'nullable',
            'pec' => 'nullable',
            'country' => 'required|size:2',
            'address' => 'nullable',
            'postal_code' => 'nullable',
            'city' => 'nullable',
            'province' => 'nullable',
        ];
    }

    public function countries(): array
    {
        return ['IT' => 'Italia', 'AT' => 'Austria', 'BE' => 'Belgio', 'BG' => 'Bulgaria', 'CY' => 'Cipro', 'HR' => 'Croazia', 'DK' => 'Danimarca', 'EE' => 'Estonia', 'FI' => 'Finlandia', 'FR' => 'Francia', 'DE' => 'Germania', 'GR' => 'Grecia', 'IE' => 'Irlanda', 'LV' => 'Lettonia', 'LT' => 'Lituania', 'LU' => 'Lussemburgo', 'MT' => 'Malta', 'NL' => 'Paesi Bassi', 'PL' => 'Polonia', 'PT' => 'Portogallo', 'CZ' => 'Repubblica Ceca', 'RO' => 'Romania', 'SK' => 'Slovacchia', 'SI' => 'Slovenia', 'ES' => 'Spagna', 'SE' => 'Svezia', 'HU' => 'Ungheria', 'CH' => 'Svizzera', 'GB' => 'Regno Unito', 'US' => 'Stati Uniti', 'CN' => 'Cina'];
    }
};
?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Anagrafiche</p><h1 class="text-lg font-bold text-content">Nuovo contatto</h1></div></x-slot:header>

<section class="mx-auto max-w-4xl"><form wire:submit="save" class="space-y-6"><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><div class="grid gap-4 sm:grid-cols-2"><x-settings.input wire:model="name" label="Nome *"/><x-select label="Nazione *" wire:model.live="country" :options="$this->countries()" /><x-settings.input wire:model="vat_number" label="Partita IVA"/><x-settings.input wire:model="tax_code" label="Codice fiscale"/><x-settings.input wire:model="email" type="email" label="Email"/><x-settings.input wire:model="pec" type="email" label="PEC"/><x-settings.input wire:model="sdi_code" label="Codice SDI"/></div></article><article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><h2 class="font-bold">Indirizzo</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><x-settings.input wire:model="address" label="Indirizzo"/></div><x-settings.input wire:model="postal_code" label="CAP"/><x-settings.input wire:model="city" label="Città"/><x-settings.input wire:model="province" label="Provincia" maxlength="2"/></div></article><div class="flex gap-3"><button class="rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white" type="submit">Crea contatto</button><x-app-link :href="route('contacts.index')" class="rounded-md border border-border px-5 py-2.5 text-sm font-semibold">Annulla</x-app-link></div></form></section>
