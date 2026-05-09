<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Rules\ItalianTaxCode;
use App\Rules\ItalianVatNumber;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Traits\Toast;

class Edit extends Component
{
    use Toast;

    public Contact $contact;

    #[Validate('required')]
    public string $name = '';

    #[Validate('nullable|email')]
    public ?string $email = null;

    public ?string $vat_number = null;

    #[Validate(['nullable', new ItalianTaxCode])]
    public ?string $tax_code = null;

    public ?string $sdi_code = null;

    public ?string $pec = null;

    public string $country = 'IT';

    public ?string $address = null;

    public ?string $postal_code = null;

    public ?string $city = null;

    public ?string $province = null;

    public function mount(Contact $contact)
    {
        $this->contact = $contact;
        $this->fill($contact->toArray());
    }

    public function save()
    {
        $this->validate();

        if ($this->country === 'IT') {
            $this->validate(['vat_number' => ['nullable', new ItalianVatNumber]]);
        }

        $this->contact->update([
            'name' => $this->name,
            'email' => $this->email,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
            'sdi_code' => $this->sdi_code,
            'pec' => $this->pec,
            'country' => $this->country,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'province' => $this->province,
        ]);

        $this->success(__('app.contacts.updated'));
        $this->redirect('/contacts', navigate: true);
    }

    public function render()
    {
        $countries = [
            ['id' => 'IT', 'name' => 'Italia'],
            ['id' => 'AT', 'name' => 'Austria'],
            ['id' => 'BE', 'name' => 'Belgio'],
            ['id' => 'BG', 'name' => 'Bulgaria'],
            ['id' => 'CY', 'name' => 'Cipro'],
            ['id' => 'HR', 'name' => 'Croazia'],
            ['id' => 'DK', 'name' => 'Danimarca'],
            ['id' => 'EE', 'name' => 'Estonia'],
            ['id' => 'FI', 'name' => 'Finlandia'],
            ['id' => 'FR', 'name' => 'Francia'],
            ['id' => 'DE', 'name' => 'Germania'],
            ['id' => 'GR', 'name' => 'Grecia'],
            ['id' => 'IE', 'name' => 'Irlanda'],
            ['id' => 'LV', 'name' => 'Lettonia'],
            ['id' => 'LT', 'name' => 'Lituania'],
            ['id' => 'LU', 'name' => 'Lussemburgo'],
            ['id' => 'MT', 'name' => 'Malta'],
            ['id' => 'NL', 'name' => 'Paesi Bassi'],
            ['id' => 'PL', 'name' => 'Polonia'],
            ['id' => 'PT', 'name' => 'Portogallo'],
            ['id' => 'CZ', 'name' => 'Repubblica Ceca'],
            ['id' => 'RO', 'name' => 'Romania'],
            ['id' => 'SK', 'name' => 'Slovacchia'],
            ['id' => 'SI', 'name' => 'Slovenia'],
            ['id' => 'ES', 'name' => 'Spagna'],
            ['id' => 'SE', 'name' => 'Svezia'],
            ['id' => 'HU', 'name' => 'Ungheria'],
            ['id' => 'CH', 'name' => 'Svizzera'],
            ['id' => 'GB', 'name' => 'Regno Unito'],
            ['id' => 'US', 'name' => 'Stati Uniti'],
            ['id' => 'CN', 'name' => 'Cina'],
        ];

        return view('livewire.contacts.edit', [
            'countries' => $countries,
        ]);
    }
}
