<div>
    <x-header :title="__('app.sequences.title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.create')" icon="o-plus" variant="primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    
        @php
            $seqHeaders = [
                ['key' => 'name',    'label' => __('app.sequences.col_name')],
                ['key' => 'pattern', 'label' => __('app.sequences.col_pattern')],
                ['key' => 'type',    'label' => __('app.sequences.col_type'), 'view' => 'partials.sequence-type-cell'],
                ['key' => 'actions', 'label' => '', 'class' => 'w-1', 'view' => 'partials.sequence-actions'],
            ];
        @endphp
        <x-table
            :rows="$sequences"
            :headers="$seqHeaders"
            with-pagination
            :selectable="true"
            :selected-ids="$selectedIds"
        >
            <x-slot:empty>
                <div class="py-8 flex flex-col items-center gap-2">
                    <x-icon name="o-inbox" class="w-8 h-8" />
                    <p class="text-sm">{{ __('app.common.empty_table') }}</p>
                </div>
            </x-slot:empty>
        </x-table>
    

    <x-modal wire:model="modal" :title="$is_editing ? __('app.sequences.edit_modal') : __('app.sequences.create_modal')">
        <x-form wire:submit="save">
            <x-input :label="__('app.sequences.name')" wire:model="name" />
            <x-select
                :label="__('app.sequences.type')"
                wire:model="type"
                :options="$typeOptions"
                option-value="value"
                option-label="label"
                :disabled="$is_editing && $sequence_id && \App\Models\Sequence::find($sequence_id)?->is_system"
            />
            <x-input
                :label="__('app.sequences.pattern')"
                wire:model="pattern"
                :hint="__('app.sequences.pattern_hint')"
            />

            <x-slot:actions>
                <x-button :label="__('app.common.cancel')" @click="$wire.modal = false" />
                <x-button :label="__('app.common.save')" variant="primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
