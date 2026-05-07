<?php

namespace App\Livewire\Sequences;

use App\Models\Sequence;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public bool $modal = false;

    public bool $is_editing = false;

    // Form fields
    public ?int $sequence_id = null;

    #[Validate('required')]
    public string $name = '';

    #[Validate('required')]
    public string $type = 'electronic_invoice';

    #[Validate('required')]
    public string $pattern = '{SEQ}';

    public function create(): void
    {
        $this->reset(['sequence_id', 'name', 'type', 'pattern']);
        $this->type = 'electronic_invoice';
        $this->pattern = '{SEQ}';
        $this->is_editing = false;
        $this->modal = true;
    }

    public function edit(Sequence $sequence): void
    {
        $this->sequence_id = $sequence->id;
        $this->name = $sequence->name;
        $this->type = $sequence->type;
        $this->pattern = $sequence->pattern;

        $this->is_editing = true;
        $this->modal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required',
                Rule::unique('sequences', 'name')
                    ->where('type', $this->type)
                    ->ignore($this->sequence_id),
            ],
            'type' => 'required',
            'pattern' => 'required',
        ]);

        if ($this->is_editing) {
            $sequence = Sequence::find($this->sequence_id);
            $sequence->update([
                'name' => $this->name,
                'type' => $this->type,
                'pattern' => $this->pattern,
            ]);
            $this->success(__('app.sequences.updated'));
        } else {
            Sequence::create([
                'name' => $this->name,
                'type' => $this->type,
                'pattern' => $this->pattern,
            ]);
            $this->success(__('app.sequences.created'));
        }

        $this->modal = false;
    }

    public function delete(Sequence $sequence): void
    {
        try {
            $sequence->delete();
            $this->success(__('app.sequences.deleted', ['name' => $sequence->name]));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.sequences.index', [
            'sequences' => Sequence::orderBy('name')->paginate(10),
            'typeOptions' => [
                ['value' => 'electronic_invoice', 'label' => __('app.sequences.type_electronic_invoice')],
                ['value' => 'purchase',           'label' => __('app.sequences.type_purchase')],
                ['value' => 'self_invoice',       'label' => __('app.sequences.type_self_invoice')],
                ['value' => 'proforma',           'label' => __('app.sequences.type_proforma')],
                ['value' => 'credit_note',        'label' => __('app.sequences.type_credit_note')],
                ['value' => 'quote',              'label' => __('app.sequences.type_quote')],
            ],
        ]);
    }
}
