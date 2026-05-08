<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\Toast;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->resetPage();
        $this->success(__('app.contacts.filters_cleared'), position: 'toast-bottom');
    }

    // Delete — prevent if contact has linked invoices
    public function delete(Contact $contact): void
    {
        if ($contact->invoices()->withoutGlobalScopes()->exists()) {
            $this->error(__('app.contacts.has_invoices'));

            return;
        }

        $contact->delete();
        $this->warning(__('app.contacts.deleted', ['name' => $contact->name]), __('app.common.goodbye'), position: 'toast-bottom');
    }

    // Headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => __('app.contacts.col_name')],
            ['key' => 'vat_number', 'label' => __('app.contacts.col_vat_number')],
            ['key' => 'email', 'label' => __('app.contacts.col_email')],
            ['key' => 'city', 'label' => __('app.contacts.col_city')],
            ['key' => 'actions', 'label' => '', 'class' => 'w-1', 'view' => 'partials.contact-actions'],
        ];
    }

    public function render()
    {
        $contacts = Contact::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%$this->search%")
                ->orWhere('vat_number', 'like', "%$this->search%")
                ->orWhere('email', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);

        return view('livewire.contacts.index', [
            'contacts' => $contacts,
            'headers' => $this->headers(),
        ]);
    }
}
