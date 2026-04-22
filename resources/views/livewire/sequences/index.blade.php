<div>
    <x-header :title="__('app.sequences.title')" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.create')" icon="o-plus" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table
            :rows="$sequences"
            :headers="[
                ['key' => 'name',    'label' => __('app.sequences.col_name')],
                ['key' => 'pattern', 'label' => __('app.sequences.col_pattern')],
                ['key' => 'type',    'label' => __('app.sequences.col_type')],
            ]"
            with-pagination
        >
            <x-slot:empty>
                <div class="py-8 flex flex-col items-center gap-2">
                    <x-icon name="o-inbox" class="w-8 h-8" />
                    <p class="text-sm">{{ __('app.common.empty_table') }}</p>
                </div>
            </x-slot:empty>

            @scope('cell_type', $sequence)
                <x-badge :value="__('app.sequences.type_' . $sequence->type)" />
            @endscope

            @scope('actions', $sequence)
                <x-button icon="o-pencil" wire:click="edit({{ $sequence->id }})" class="btn-ghost btn-sm" />
                @if(!$sequence->is_system)
                    <x-button icon="o-trash" wire:click="delete({{ $sequence->id }})" wire:confirm="{{ __('app.common.confirm_delete') }}" class="btn-ghost btn-sm text-red-500" />
                @endif
            @endscope
        </x-table>
    </x-card>

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
                <x-button :label="__('app.common.save')" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
