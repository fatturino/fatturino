{{-- Merged timeline: audits + SDI logs, clustered for consecutive line edits --}}
<div class="py-4">
    @if (count($clusters) === 0)
        <p class="text-sm text-base-content/60">{{ __('app.audit.timeline_empty') }}</p>
    @else
        <div class="space-y-1">
            @foreach ($clusters as $index => $cluster)
                @php
                    $isLast = $index === array_key_last($clusters);
                    $isFirst = $index === 0;
                    $first = $cluster['items'][0];
                    $isGrouped = count($cluster['items']) > 1;
                    $isExpanded = $expanded[$cluster['key']] ?? false;
                @endphp

                @if ($isGrouped)
                    <x-timeline-item
                        :title="__('app.audit.grouped_line_changes', ['count' => count($cluster['items'])])"
                        :subtitle="$first['at']->translatedFormat('d M Y H:i') . ' · ' . ($first['user_name'] ?? __('app.audit.system'))"
                        icon="o-rectangle-stack"
                        :first="$isFirst"
                        :last="$isLast && ! $isExpanded"
                    >
                        <x-slot:description>
                            <x-button
                                :label="$isExpanded ? __('app.audit.collapse') : __('app.audit.expand')"
                                class="btn-ghost btn-xs"
                                wire:click="toggleCluster('{{ $cluster['key'] }}')"
                            />
                        </x-slot:description>
                    </x-timeline-item>

                    @if ($isExpanded)
                        <div class="ms-6 border-s-2 border-base-300/40 ps-4 py-2 space-y-1">
                            @foreach ($cluster['items'] as $subItem)
                                @include('livewire.invoices.partials.timeline-entry', [
                                    'entry' => $subItem,
                                    'isNested' => true,
                                ])
                            @endforeach
                        </div>
                    @endif
                @else
                    @include('livewire.invoices.partials.timeline-entry', [
                        'entry' => $first,
                        'isNested' => false,
                        'isFirst' => $isFirst,
                        'isLast' => $isLast,
                    ])
                @endif
            @endforeach
        </div>
    @endif
</div>
