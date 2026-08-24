<?php

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'name')]
    public string $sort = 'name';

    #[Url(as: 'direction', except: 'asc')]
    public string $direction = 'asc';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'sort', 'direction'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'vat_number', 'email', 'city'], true)) {
            return;
        }
        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function resetFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function render()
    {
        $contacts = $this->query()
            ->orderBy($this->sortableColumn(), $this->direction === 'desc' ? 'desc' : 'asc')
            ->paginate(15);

        return view('pages::contacts.index', compact('contacts'));
    }

    private function query(): Builder
    {
        return Contact::query()->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('name', 'like', '%'.$this->search.'%')
            ->orWhere('vat_number', 'like', '%'.$this->search.'%')
            ->orWhere('email', 'like', '%'.$this->search.'%')));
    }

    private function sortableColumn(): string
    {
        return in_array($this->sort, ['name', 'vat_number', 'email', 'city'], true) ? $this->sort : 'name';
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">Anagrafiche</p>
        <h1 class="text-lg font-semibold text-content">Contatti</h1>
    </div>
</x-slot:header>

<section class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-content">{{ $contacts->total() }} {{ $contacts->total() === 1 ? 'contatto' : 'contatti' }}</p>
            <p class="mt-1 text-sm text-content-muted">Clienti e fornitori registrati.</p>
        </div>

        <x-app-link :href="route('contacts.create')" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20">
            Nuovo contatto
        </x-app-link>
    </div>

    <div class="flex flex-col gap-3 border-b border-border pb-5 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xl">
            <label for="contact-search" class="sr-only">Cerca contatti</label>
            <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-content-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
            </svg>
            <input id="contact-search" wire:model.live.debounce.350ms="search" type="search" class="block h-11 w-full rounded-lg border border-border-strong bg-white py-2 pl-10 pr-3 text-sm text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Cerca per nome, P.IVA o email">
        </div>

        @if($search !== '')
            <button wire:click="resetFilters" type="button" class="inline-flex h-11 items-center justify-center rounded-lg px-3 text-sm font-medium text-content-muted transition hover:bg-surface-muted hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20">
                Cancella ricerca
            </button>
        @endif
    </div>

    <div class="overflow-x-auto border-y border-border bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-border bg-surface-muted text-content-muted">
                <tr>
                    @foreach (['name' => 'Nome', 'vat_number' => 'P.IVA', 'email' => 'Email', 'city' => 'Città'] as $column => $label)
                        @php
                            $isActiveSort = $sort === $column;
                            $ariaSort = $isActiveSort ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
                        @endphp
                        <th scope="col" aria-sort="{{ $ariaSort }}" class="px-5 py-3 text-xs font-medium">
                            <button wire:click="sortBy('{{ $column }}')" type="button" class="inline-flex min-h-6 items-center gap-1.5 rounded text-left transition hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="Ordina per {{ $label }}">
                                <span>{{ $label }}</span>
                                @if($isActiveSort)
                                    <x-icon :name="$direction === 'asc' ? 'o-chevron-up' : 'o-chevron-down'" class="size-3.5 text-primary" />
                                @else
                                    <x-icon name="o-chevron-up-down" class="size-3.5 opacity-40" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($contacts as $contact)
                    <tr class="transition-colors hover:bg-surface-muted/70 focus-within:bg-primary-subtle">
                        <td class="px-5 py-3.5 font-medium text-content">
                            <x-app-link :href="route('contacts.edit', $contact)" class="rounded text-content transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                {{ $contact->name }}
                            </x-app-link>
                        </td>
                        <td class="px-5 py-3.5 text-content-muted">{{ $contact->vat_number ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-content-muted">{{ $contact->email ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-content-muted">{{ $contact->city ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-sm text-content-muted">
                            {{ $search !== '' ? 'Nessun contatto corrisponde alla ricerca.' : 'Nessun contatto ancora registrato.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($contacts->hasPages())
        <div class="pt-1">{{ $contacts->links() }}</div>
    @endif
</section>
