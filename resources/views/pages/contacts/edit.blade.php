<?php

use App\Models\Contact;
use App\Rules\ItalianTaxCode;
use App\Rules\ItalianVatNumber;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public Contact $contact;

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

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
        foreach (array_keys($this->rules()) as $field) {
            $this->{$field} = $contact->{$field} ?? '';
        }
    }

    public function save(): mixed
    {
        $this->contact->update($this->validate());
        session()->flash('success', 'Contatto aggiornato.');

        return $this->redirectRoute('contacts.index', navigate: true);
    }

    protected function rules(): array
    {
        return ['name' => 'required|string', 'email' => 'nullable|email', 'vat_number' => $this->country === 'IT' ? ['nullable', new ItalianVatNumber] : 'nullable', 'tax_code' => ['nullable', new ItalianTaxCode], 'sdi_code' => 'nullable', 'pec' => 'nullable', 'country' => 'required|size:2', 'address' => 'nullable', 'postal_code' => 'nullable', 'city' => 'nullable', 'province' => 'nullable'];
    }

    public function countries(): array
    {
        return ['IT' => 'Italia', 'AT' => 'Austria', 'BE' => 'Belgio', 'BG' => 'Bulgaria', 'CY' => 'Cipro', 'HR' => 'Croazia', 'DK' => 'Danimarca', 'EE' => 'Estonia', 'FI' => 'Finlandia', 'FR' => 'Francia', 'DE' => 'Germania', 'GR' => 'Grecia', 'IE' => 'Irlanda', 'LV' => 'Lettonia', 'LT' => 'Lituania', 'LU' => 'Lussemburgo', 'MT' => 'Malta', 'NL' => 'Paesi Bassi', 'PL' => 'Polonia', 'PT' => 'Portogallo', 'CZ' => 'Repubblica Ceca', 'RO' => 'Romania', 'SK' => 'Slovacchia', 'SI' => 'Slovenia', 'ES' => 'Spagna', 'SE' => 'Svezia', 'HU' => 'Ungheria', 'CH' => 'Svizzera', 'GB' => 'Regno Unito', 'US' => 'Stati Uniti', 'CN' => 'Cina'];
    }
};
?>

<x-slot:header><div><p class="text-xs font-medium text-content-muted">Anagrafiche</p><h1 class="text-lg font-semibold text-content">Modifica contatto</h1></div></x-slot:header>

<x-contacts.form submit-label="Salva contatto" />
