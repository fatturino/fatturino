<div>
    <x-header :title="__('app.audit.index.title')" separator />

    {{-- Filter bar --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">
        <x-select
            :label="__('app.audit.index.filter_user')"
            :options="$users"
            option-value="id"
            option-label="name"
            wire:model.live="filterUserId"
            :placeholder="__('app.common.all')"
        />
        <x-select
            :label="__('app.audit.index.filter_event')"
            :options="$eventOptions"
            option-value="id"
            option-label="name"
            wire:model.live="filterEvent"
            :placeholder="__('app.common.all')"
        />
        <x-select
            :label="__('app.audit.index.filter_entity')"
            :options="$auditableTypes"
            option-value="id"
            option-label="name"
            wire:model.live="filterAuditableType"
            :placeholder="__('app.common.all')"
        />
        <x-datetime
            :label="__('app.audit.index.filter_from')"
            wire:model.live="filterDateFrom"
            type="date"
        />
        <x-datetime
            :label="__('app.audit.index.filter_to')"
            wire:model.live="filterDateTo"
            type="date"
        />
    </div>

    <div class="mb-3 flex justify-end">
        <x-button :label="__('app.common.reset')" wire:click="clearFilters" icon="o-x-mark" variant="ghost" size="sm" />
    </div>

    {{-- Audit table --}}
    @if ($audits->isEmpty())
        <p class="p-6 text-center text-base-content/60">{{ __('app.common.empty_table') }}</p>
    @else
        <div class="overflow-x-auto border border-base-300 rounded-lg">
            <table class="min-w-full divide-y divide-base-300 text-sm">
                <thead class="bg-base-200">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider">{{ __('app.audit.index.column_date') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider">{{ __('app.audit.index.column_user') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider">{{ __('app.audit.index.column_event') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider">{{ __('app.audit.index.column_entity') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider">{{ __('app.audit.index.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-300 bg-white">
                    @foreach ($audits as $audit)
                        @php
                            $eventKey = 'app.audit.events.' . $audit->event;
                            $eventLabel = __($eventKey);
                            if ($eventLabel === $eventKey) {
                                $eventLabel = $audit->event;
                            }
                        @endphp
                        <tr>
                            <td class="px-5 py-3 text-sm">{{ $audit->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-sm">{{ $audit->user?->name ?? __('app.audit.system') }}</td>
                            <td class="px-5 py-3 text-sm">{{ $eventLabel }}</td>
                            <td class="px-5 py-3 text-sm">
                                <span class="font-mono text-xs">{{ class_basename($audit->auditable_type) }}#{{ $audit->auditable_id }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <x-button
                                    :label="__('app.audit.index.details')"
                                    variant="ghost" size="xs"
                                    @click="$dispatch('audit-detail', { id: {{ $audit->id }} })"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $audits->links() }}
        </div>
    @endif

    {{-- Detail modal --}}
    <x-modal wire:model="detailModal" :title="$eventLabel ?? __('app.audit.index.details')">
        @if(isset($detailAudit))
            <p class="text-xs text-base-content/60 mb-4">
                {{ $detailAudit->created_at->translatedFormat('d M Y H:i:s') }}
                · {{ $detailAudit->user?->name ?? __('app.audit.system') }}
            </p>

            @if (empty($detailAudit->old_values) && empty($detailAudit->new_values))
                <p class="text-sm">{{ __('app.audit.index.no_changes') }}</p>
            @else
                <div class="overflow-x-auto border border-base-300 rounded-lg">
                    <table class="min-w-full divide-y divide-base-300 text-sm">
                        <thead class="bg-base-200">
                            <tr>
                                <th class="px-4 py-2 text-xs font-semibold text-left">{{ __('app.audit.index.column_entity') }}</th>
                                <th class="px-4 py-2 text-xs font-semibold text-left">{{ __('app.audit.index.old_value') }}</th>
                                <th class="px-4 py-2 text-xs font-semibold text-left">{{ __('app.audit.index.new_value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300 bg-white">
                            @foreach (array_keys(array_merge($detailAudit->old_values ?? [], $detailAudit->new_values ?? [])) as $field)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $field }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $detailAudit->old_values[$field] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $detailAudit->new_values[$field] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        <x-slot:actions>
            <x-button :label="__('app.common.close')" @click="$wire.detailModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
