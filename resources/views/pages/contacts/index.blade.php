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
        $aggregates = $this->aggregates();

        return view('pages::contacts.index', compact('contacts', 'aggregates'));
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

    private function aggregates(): object
    {
        return Contact::query()->selectRaw("
            count(*) as total,
            sum(case when is_customer then 1 else 0 end) as customers,
            sum(case when is_supplier then 1 else 0 end) as suppliers,
            sum(case when
                (vat_number is null or vat_number = '')
                and (tax_code is null or tax_code = '')
                or (
                    is_customer
                    and country = 'IT'
                    and (sdi_code is null or sdi_code = '')
                    and (pec is null or pec = '')
                )
                then 1 else 0 end) as incomplete
        ")->firstOrFail();
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">Anagrafiche</p>
        <h1 class="text-lg font-semibold text-content">Contatti</h1>
    </div>
</x-slot:header>

<section class="space-y-5">
    <x-page-summary
        eyebrow="Anagrafiche"
        title="Riepilogo contatti"
        title-id="contact-summary-title"
    >
        <x-slot:actions>
            <x-button
                label="Nuovo contatto"
                :link="route('contacts.create')"
                size="cta"
                class="shrink-0 active:translate-y-px"
            />
        </x-slot:actions>

        <x-page-summary.stat
            label="Contatti"
            :value="$aggregates->total"
            detail="Clienti e fornitori"
            value-id="contact-summary-total"
        />
        <x-page-summary.stat
            label="Clienti"
            :value="$aggregates->customers"
            detail="Abilitati alle vendite"
            value-id="contact-summary-customers"
        />
        <x-page-summary.stat
            label="Fornitori"
            :value="$aggregates->suppliers"
            detail="Abilitati agli acquisti"
            value-id="contact-summary-suppliers"
        />
        <x-page-summary.stat
            label="Da completare"
            :value="$aggregates->incomplete"
            :detail="$aggregates->incomplete > 0 ? 'Dati fiscali o recapiti mancanti' : 'Anagrafiche complete'"
            :detail-class="$aggregates->incomplete > 0 ? 'text-warning' : 'text-content-muted'"
            value-id="contact-summary-incomplete"
        />
    </x-page-summary>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xl">
            <label for="contact-search" class="sr-only">Cerca contatti</label>
            <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-content-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
            </svg>
            <input id="contact-search" wire:model.live.debounce.350ms="search" type="search" class="block h-11 w-full rounded-lg border border-border-strong bg-white py-2 pl-10 pr-3 text-sm text-content placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Cerca per nome, P.IVA o email">
        </div>

        @if($search !== '')
            <x-button label="Cancella ricerca" wire:click="resetFilters" variant="ghost" size="cta" />
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
                        <td colspan="4">
                            <x-empty-state
                                class="py-12"
                                :title="$search !== '' ? 'Nessun contatto corrisponde alla ricerca.' : 'Nessun contatto ancora registrato.'"
                            />
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
