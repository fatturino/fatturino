<?php

use App\Contracts\EnvironmentCapabilities;
use App\Models\Sequence;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    public string $name = '';

    public string $type = 'sales';

    public string $pattern = '{SEQ}';

    public ?int $editingId = null;

    public function storeSequence(): void
    {
        $this->ensureAllowed();
        $validated = $this->validate($this->rules());
        Sequence::create($validated);
        $this->resetForm();
        session()->flash('success', 'Sezionale creato.');
    }

    public function edit(Sequence $sequence): void
    {
        $this->editingId = $sequence->id;
        $this->name = $sequence->name;
        $this->type = $sequence->type;
        $this->pattern = $sequence->pattern;
        $this->resetValidation();
    }

    public function update(): void
    {
        $this->ensureAllowed();
        $sequence = Sequence::findOrFail($this->editingId);
        if ($sequence->is_system) {
            $this->type = $sequence->type;
        }
        $sequence->update($this->validate($this->rules($sequence)));
        $this->resetForm();
        session()->flash('success', 'Sezionale aggiornato.');
    }

    public function delete(Sequence $sequence): void
    {
        $this->ensureAllowed();
        try {
            $sequence->delete();
            session()->flash('success', 'Sezionale eliminato.');
        } catch (Exception $exception) {
            $this->addError('sequence', $exception->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        return view('pages::settings.sequences', [
            'sequences' => Sequence::orderBy('name')->paginate(10),
            'canManage' => app(EnvironmentCapabilities::class)->can('manage-sequences'),
        ]);
    }

    private function rules(?Sequence $sequence = null): array
    {
        return [
            'name' => ['required', Rule::unique('sequences', 'name')->where('type', $this->type)->ignore($sequence?->id)],
            'type' => ['required', Rule::in(array_column($this->typeOptions(), 'value'))],
            'pattern' => ['required', 'string'],
        ];
    }

    private function typeOptions(): array
    {
        return [
            ['value' => 'sales', 'label' => __('app.sequences.type_sales')],
            ['value' => 'purchase', 'label' => __('app.sequences.type_purchase')],
            ['value' => 'self_invoice', 'label' => __('app.sequences.type_self_invoice')],
            ['value' => 'proforma', 'label' => __('app.sequences.type_proforma')],
            ['value' => 'credit_note', 'label' => __('app.sequences.type_credit_note')],
            ['value' => 'quote', 'label' => __('app.sequences.type_quote')],
        ];
    }

    private function ensureAllowed(): void
    {
        abort_unless(app(EnvironmentCapabilities::class)->can('manage-sequences'), 403, 'Operazione non consentita in questa modalità.');
    }

    private function resetForm(): void
    {
        $this->reset('name', 'editingId');
        $this->type = 'sales';
        $this->pattern = '{SEQ}';
        $this->resetValidation();
    }
};
?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Numerazione documenti</p><h1 class="text-lg font-bold text-content">Sequenze</h1></div></x-slot:header>

<section class="space-y-6">
    @if(session('success'))<div class="rounded-md border border-success/20 bg-success-bg p-4 text-sm text-success">{{ session('success') }}</div>@endif
    @error('sequence')<div class="rounded-md border border-danger/20 bg-danger-bg p-4 text-sm text-danger">{{ $message }}</div>@enderror

    <article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
        <h2 class="font-bold">{{ $editingId ? 'Modifica sezionale' : 'Nuovo sezionale' }}</h2>
        @if($canManage)
            <form wire:submit="{{ $editingId ? 'update' : 'storeSequence' }}" class="mt-4 grid gap-4 md:grid-cols-4">
                <label class="block text-sm font-semibold text-content">Nome<input wire:model="name" class="mt-2 block w-full rounded-md border border-border px-3 py-2" type="text">@error('name')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-semibold text-content">Tipo<select wire:model="type" @disabled($editingId && Sequence::find($editingId)?->is_system) class="mt-2 block w-full rounded-md border border-border px-3 py-2">@foreach($this->typeOptions() as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</select>@error('type')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-semibold text-content">Formato<input wire:model="pattern" class="mt-2 block w-full rounded-md border border-border px-3 py-2 font-mono" type="text">@error('pattern')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror</label>
                <div class="flex items-end gap-2"><button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-bold text-white" wire:loading.attr="disabled">{{ $editingId ? 'Aggiorna' : 'Crea' }}</button>@if($editingId)<button wire:click="cancel" type="button" class="rounded-md border border-border px-4 py-2 text-sm font-semibold">Annulla</button>@endif</div>
            </form>
        @else
            <p class="mt-2 text-sm text-content-muted">La configurazione è in sola lettura in questa modalità.</p>
        @endif
    </article>

    <article class="overflow-hidden rounded-xl border border-border-light bg-white shadow-[var(--shadow-card)]">
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-surface-muted text-xs uppercase tracking-wide text-content-muted"><tr><th class="px-5 py-3">Nome</th><th class="px-5 py-3">Formato</th><th class="px-5 py-3">Tipo</th><th class="px-5 py-3 text-right">Azioni</th></tr></thead><tbody class="divide-y divide-border-light">@forelse($sequences as $sequence)<tr><td class="px-5 py-4 font-semibold">{{ $sequence->name }} @if($sequence->is_system)<span class="ml-2 rounded-full bg-surface-muted px-2 py-1 text-xs text-content-muted">Sistema</span>@endif</td><td class="px-5 py-4 font-mono text-content-muted">{{ $sequence->pattern }}</td><td class="px-5 py-4 text-content-muted">{{ collect($this->typeOptions())->firstWhere('value', $sequence->type)['label'] ?? $sequence->type }}</td><td class="px-5 py-4 text-right">@if($canManage)<button wire:click="edit({{ $sequence->id }})" class="text-sm font-semibold text-primary">Modifica</button>@if(! $sequence->is_system)<button wire:click="delete({{ $sequence->id }})" wire:confirm="Eliminare il sezionale '{{ $sequence->name }}'?" class="ml-4 text-sm font-semibold text-danger">Elimina</button>@endif@endif</td></tr>@empty<tr><td colspan="4" class="px-5 py-12 text-center text-sm text-content-muted">Nessun sezionale ancora creato.</td></tr>@endforelse</tbody></table></div>
        @if($sequences->hasPages())<div class="border-t border-border-light px-5 py-4">{{ $sequences->links() }}</div>@endif
    </article>
</section>
