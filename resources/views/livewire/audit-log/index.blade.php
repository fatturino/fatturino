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
        <x-button :label="__('app.common.reset')" wire:click="clearFilters" icon="o-x-mark" class="btn-ghost btn-sm" />
    </div>

    {{-- Audit table --}}
    <x-card>
        @if ($audits->isEmpty())
            <p class="p-6 text-center text-base-content/60">{{ __('app.common.empty_table') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('app.audit.index.column_date') }}</th>
                            <th>{{ __('app.audit.index.column_user') }}</th>
                            <th>{{ __('app.audit.index.column_event') }}</th>
                            <th>{{ __('app.audit.index.column_entity') }}</th>
                            <th>{{ __('app.audit.index.column_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            @php
                                $eventKey = 'app.audit.events.' . $audit->event;
                                $eventLabel = __($eventKey);
                                if ($eventLabel === $eventKey) {
                                    $eventLabel = $audit->event;
                                }
                            @endphp
                            <tr>
                                <td class="text-sm">{{ $audit->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-sm">{{ $audit->user?->name ?? __('app.audit.system') }}</td>
                                <td class="text-sm">{{ $eventLabel }}</td>
                                <td class="text-sm">
                                    <span class="font-mono text-xs">{{ class_basename($audit->auditable_type) }}#{{ $audit->auditable_id }}</span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs"
                                        onclick="document.getElementById('audit-modal-{{ $audit->id }}').showModal()"
                                    >
                                        {{ __('app.audit.index.details') }}
                                    </button>

                                    <dialog id="audit-modal-{{ $audit->id }}" class="modal">
                                        <div class="modal-box max-w-2xl">
                                            <h3 class="font-bold text-lg mb-3">{{ $eventLabel }}</h3>
                                            <p class="text-xs text-base-content/60 mb-4">
                                                {{ $audit->created_at->translatedFormat('d M Y H:i:s') }}
                                                · {{ $audit->user?->name ?? __('app.audit.system') }}
                                            </p>

                                            @if (empty($audit->old_values) && empty($audit->new_values))
                                                <p class="text-sm">{{ __('app.audit.index.no_changes') }}</p>
                                            @else
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('app.audit.index.column_entity') }}</th>
                                                            <th>{{ __('app.audit.index.old_value') }}</th>
                                                            <th>{{ __('app.audit.index.new_value') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach (array_keys(array_merge($audit->old_values ?? [], $audit->new_values ?? [])) as $field)
                                                            <tr>
                                                                <td class="font-mono text-xs">{{ $field }}</td>
                                                                <td class="text-xs">{{ $audit->old_values[$field] ?? '—' }}</td>
                                                                <td class="text-xs">{{ $audit->new_values[$field] ?? '—' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif

                                            <div class="modal-action">
                                                <form method="dialog">
                                                    <button class="btn btn-sm">{{ __('app.common.close') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                                    </dialog>
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
    </x-card>
</div>
