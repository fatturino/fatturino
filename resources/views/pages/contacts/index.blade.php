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
            ->where('name', 'like', "%
{
$this->search
}
%")
            ->orWhere('vat_number', 'like', "%
{
$this->search
}
%")
            ->orWhere('email', 'like', "%
{
$this->search
}
%")));
    }

    private function sortableColumn(): string
    {
        return in_array($this->sort, ['name', 'vat_number', 'email', 'city'], true) ? $this->sort : 'name';
    }
};
?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Anagrafiche</p><h1 class="text-lg font-bold text-content">Contatti</h1></div></x-slot:header>

<section class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Rubrica</p><p class="mt-1 text-sm text-content-muted">Gestisci clienti e fornitori.</p></div><a href="{{ route('contacts.create') }}" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white">Nuovo contatto</a></div>
    <article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)]"><div class="border-b border-border-light p-4"><div class="flex flex-col gap-3 sm:flex-row"><div class="relative w-full"><label for="contact-search" class="sr-only">Cerca contatti</label><svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-content-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="contact-search" wire:model.live.debounce.350ms="search" type="search" class="block h-11 w-full rounded-md border border-border bg-white py-2 pl-10 pr-3 text-sm text-content shadow-sm placeholder:text-content-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Cerca per nome, P.IVA o email"></div><button wire:click="resetFilters" class="h-11 rounded-md border border-border bg-white px-4 py-2 text-sm font-semibold text-content shadow-sm hover:bg-surface-muted">Reset</button></div></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-muted text-xs uppercase tracking-wide text-content-muted"><tr><th class="px-5 py-3"><button wire:click="sortBy('name')">Nome</button></th><th class="px-5 py-3"><button wire:click="sortBy('vat_number')">P.IVA</button></th><th class="px-5 py-3"><button wire:click="sortBy('email')">Email</button></th><th class="px-5 py-3"><button wire:click="sortBy('city')">Città</button></th><th class="px-5 py-3 text-right">Azioni</th></tr></thead><tbody class="divide-y divide-border-light">@forelse($contacts as $contact)<tr class="hover:bg-surface-muted/60"><td class="px-5 py-4 font-semibold">{{ $contact->name }}</td><td class="px-5 py-4 text-content-muted">{{ $contact->vat_number ?: '—' }}</td><td class="px-5 py-4 text-content-muted">{{ $contact->email ?: '—' }}</td><td class="px-5 py-4 text-content-muted">{{ $contact->city ?: '—' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('contacts.edit', $contact) }}" class="text-sm font-semibold text-primary">Apri</a></td></tr>@empty<tr><td colspan="5" class="px-5 py-12 text-center text-sm text-content-muted">Nessun contatto trovato.</td></tr>@endforelse</tbody></table></div>
        @if($contacts->hasPages())<div class="border-t border-border-light px-5 py-4">{{ $contacts->links() }}</div>@endif
    </article>
</section>
