@php
    /**
     * Renders a single timeline entry.
     * Expects: $entry (array), $isNested (bool), optionally $isFirst / $isLast (bool).
     */
    $isNested = $isNested ?? false;
    $isFirst = $isFirst ?? false;
    $isLast = $isLast ?? false;

    $eventKey = $entry['source'] === 'sdi' ? 'sdi_' . $entry['event'] : $entry['event'];
    $title = __('app.audit.events.' . $eventKey);

    // Fallback to the raw event name when no translation matches.
    if ($title === 'app.audit.events.' . $eventKey) {
        $title = $eventKey;
    }

    $subtitleTime = $entry['at']->translatedFormat('d M Y H:i');
    $actor = $entry['user_name'] ?? __('app.audit.system');
    $subtitle = "{$subtitleTime} · {$actor}";

    $icon = match (true) {
        $entry['source'] === 'sdi'                   => 'o-paper-airplane',
        str_starts_with($entry['event'], 'email_')   => 'o-envelope',
        str_starts_with($entry['event'], 'sdi_')     => 'o-paper-airplane',
        $entry['event'] === 'created'                => 'o-plus-circle',
        $entry['event'] === 'deleted'                => 'o-trash',
        $entry['event'] === 'updated'                => 'o-pencil-square',
        default                                      => 'o-clock',
    };

    $hasDiff = $entry['source'] === 'audit'
        && $entry['event'] === 'updated'
        && ! empty($entry['new_values']);
@endphp

@if ($isNested)
    <div class="flex items-start gap-2 text-sm py-1">
        <x-icon :name="$icon" class="w-4 h-4 mt-0.5 text-base-content/60" />
        <div class="flex-1">
            <div class="font-medium">{{ $title }}</div>
            <div class="text-xs text-base-content/50">{{ $subtitle }}</div>
            @if ($hasDiff)
                <div class="text-xs mt-1 text-base-content/70">
                    @foreach ($entry['new_values'] as $field => $newVal)
                        <div>
                            <span class="font-mono">{{ $field }}</span>:
                            <span class="line-through text-error/70">{{ $entry['old_values'][$field] ?? '—' }}</span>
                            →
                            <span class="text-success">{{ $newVal }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <x-timeline-item
        :title="$title"
        :subtitle="$subtitle"
        :icon="$icon"
        :first="$isFirst"
        :last="$isLast"
    >
        @if ($hasDiff || ($entry['source'] === 'sdi' && ! empty($entry['message'])))
            <x-slot:description>
                @if ($entry['source'] === 'sdi' && ! empty($entry['message']))
                    <span class="text-xs text-base-content/70">{{ $entry['message'] }}</span>
                @endif
                @if ($hasDiff)
                    <div class="text-xs mt-1 space-y-0.5">
                        @foreach ($entry['new_values'] as $field => $newVal)
                            <div>
                                <span class="font-mono text-base-content/60">{{ $field }}</span>:
                                <span class="line-through text-error/70">{{ $entry['old_values'][$field] ?? '—' }}</span>
                                →
                                <span class="text-success">{{ $newVal }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-slot:description>
        @endif
    </x-timeline-item>
@endif
