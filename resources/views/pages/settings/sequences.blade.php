<?php

use App\Contracts\EnvironmentCapabilities;
use App\Models\Sequence;
use App\Services\DocumentSequenceResolver;
use App\Settings\InvoiceSettings;
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
        abort_if($this->isDefault($sequence), 403, 'Il sezionale predefinito non è modificabile.');
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
        abort_if($this->isDefault($sequence), 403, 'Il sezionale predefinito non è modificabile.');
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
        if ($this->isDefault($sequence)) {
            $this->addError('sequence', 'Impossibile eliminare il sezionale predefinito. Imposta prima un altro sezionale come predefinito.');

            return;
        }
        try {
            $sequence->delete();
            session()->flash('success', 'Sezionale eliminato.');
        } catch (Exception $exception) {
            $this->addError('sequence', $exception->getMessage());
        }
    }

    public function setDefault(Sequence $sequence, DocumentSequenceResolver $resolver): void
    {
        $this->ensureAllowed();
        $settingKey = $resolver->settingKey($sequence->type);
        abort_unless($settingKey !== null, 422, 'Questo tipo di documento non ha una sequenza predefinita configurabile.');
        $settings = app(InvoiceSettings::class);
        $settings->{$settingKey} = $sequence->id;
        $settings->save();
        session()->flash('success', "Sezionale predefinito per
{
$this->typeLabel($sequence->type)
}
 aggiornato.");
    }

    public function isDefault(Sequence $sequence): bool
    {
        return app(DocumentSequenceResolver::class)->isDefault($sequence);
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

    private function typeLabel(string $type): string
    {
        return collect($this->typeOptions())->firstWhere('value', $type)['label'] ?? $type;
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
    @if(session('success'))
        <x-alert variant="success">{{ session('success') }}</x-alert>
    @endif
    @error('sequence')
        <x-alert variant="danger">{{ $message }}</x-alert>
    @enderror

    <x-settings.panel :title="$editingId ? 'Modifica sezionale' : 'Nuovo sezionale'">
        @if($canManage)
            <form wire:submit="{{ $editingId ? 'update' : 'storeSequence' }}" class="grid gap-4 md:grid-cols-4">
                <x-settings.input wire:model="name" label="Nome" />
                <label class="block text-sm font-medium text-content">
                    Tipo
                    <x-select wire:model="type" :disabled="$editingId && Sequence::find($editingId)?->is_system" :options="$this->typeOptions()" option-value="value" option-label="label" />
                    @error('type')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror
                </label>
                <x-settings.input wire:model="pattern" label="Formato" class="font-mono" />
                <div class="flex items-end gap-2">
                    <x-button type="submit" :label="$editingId ? 'Aggiorna' : 'Crea'" size="cta" :spinner="$editingId ? 'update' : 'storeSequence'" />
                    @if($editingId)
                        <x-button label="Annulla" wire:click="cancel" variant="secondary" size="cta" />
                    @endif
                </div>
            </form>
        @else
            <p class="text-sm text-content-muted">La configurazione è in sola lettura in questa modalità.</p>
        @endif
    </x-settings.panel>

    <x-settings.panel flush>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-surface-muted text-content-muted">
                    <tr>
                        <th class="px-5 py-3 text-xs font-medium">Nome</th>
                        <th class="px-5 py-3 text-xs font-medium">Formato</th>
                        <th class="px-5 py-3 text-xs font-medium">Tipo</th>
                        <th class="px-5 py-3 text-right text-xs font-medium">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($sequences as $sequence)
                        <tr class="transition-colors hover:bg-surface-muted/70">
                            <td class="px-5 py-4 font-semibold text-content">
                                {{ $sequence->name }}
                                @if($sequence->is_system)<x-badge value="Sistema" variant="neutral" class="ml-2 align-middle" />@endif
                                @if($this->isDefault($sequence))<x-badge value="Predefinito" variant="primary" class="ml-2 align-middle" />@endif
                            </td>
                            <td class="px-5 py-4 font-mono text-content-muted">{{ $sequence->pattern }}</td>
                            <td class="px-5 py-4 text-content-muted">{{ $this->typeLabel($sequence->type) }}</td>
                            <td class="px-5 py-4 text-right">
                                @if($canManage)
                                    @if(! $this->isDefault($sequence) && app(DocumentSequenceResolver::class)->settingKey($sequence->type))
                                        <button wire:click="setDefault({{ $sequence->id }})" type="button" class="text-sm font-semibold text-primary">Imposta predefinito</button>
                                    @endif
                                    @if(! $this->isDefault($sequence))
                                        <button wire:click="edit({{ $sequence->id }})" type="button" class="ml-4 text-sm font-semibold text-primary">Modifica</button>
                                        @if(! $sequence->is_system)
                                            <button
                                                type="button"
                                                class="ml-4 text-sm font-semibold text-danger"
                                                @click="$dispatch('confirm-dialog', { message: @js('Eliminare il sezionale \''.$sequence->name.'\'?'), callback: () => $wire.delete({{ $sequence->id }}) })"
                                            >Elimina</button>
                                        @endif
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state class="py-12" title="Nessun sezionale ancora creato." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sequences->hasPages())
            <div class="border-t border-border-light px-5 py-4">{{ $sequences->links() }}</div>
        @endif
    </x-settings.panel>
</section>
