<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Rules\ItalianTaxCode;
use App\Rules\ItalianVatNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search', '');

        $contacts = Contact::query()
            ->when($search, fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('vat_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Contacts/ContactsIndex', [
            'contacts' => $contacts,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Contacts/ContactsCreate', [
            'countries' => $this->countries(),
        ]);
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('Contacts/ContactsEdit', [
            'contact' => $contact,
            'countries' => $this->countries(),
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $rules = [
            'name' => 'required',
            'email' => 'nullable|email',
            'vat_number' => 'nullable',
            'tax_code' => ['nullable', new ItalianTaxCode],
            'sdi_code' => 'nullable',
            'pec' => 'nullable',
            'country' => 'required|size:2',
            'address' => 'nullable',
            'postal_code' => 'nullable',
            'city' => 'nullable',
            'province' => 'nullable',
        ];

        if ($request->country === 'IT') {
            $rules['vat_number'] = ['nullable', new ItalianVatNumber];
        }

        $request->validate($rules);

        $contact->update([
            'name' => $request->name,
            'email' => $request->email,
            'vat_number' => $request->vat_number,
            'tax_code' => $request->tax_code,
            'sdi_code' => $request->sdi_code,
            'pec' => $request->pec,
            'country' => $request->country,
            'address' => $request->address,
            'postal_code' => $request->postal_code,
            'city' => $request->city,
            'province' => $request->province,
        ]);

        return redirect()->route('contacts.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => 'required',
            'email' => 'nullable|email',
            'vat_number' => 'nullable',
            'tax_code' => ['nullable', new ItalianTaxCode],
            'sdi_code' => 'nullable',
            'pec' => 'nullable',
            'country' => 'required|size:2',
            'address' => 'nullable',
            'postal_code' => 'nullable',
            'city' => 'nullable',
            'province' => 'nullable',
        ];

        if ($request->country === 'IT') {
            $rules['vat_number'] = ['nullable', new ItalianVatNumber];
        }

        $request->validate($rules);

        Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'vat_number' => $request->vat_number,
            'tax_code' => $request->tax_code,
            'sdi_code' => $request->sdi_code,
            'pec' => $request->pec,
            'country' => $request->country,
            'address' => $request->address,
            'postal_code' => $request->postal_code,
            'city' => $request->city,
            'province' => $request->province,
        ]);

        return redirect()->route('contacts.index');
    }

    private function countries(): array
    {
        return [
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
    }
}
