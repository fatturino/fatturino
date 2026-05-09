@props([
    'headers' => [],
    'rows' => [],
    'sortBy' => null,
    'withPagination' => false,
    'link' => null,
])

@php
// Build link URL by replacing {key} placeholders with actual row values
$buildLink = function(array $row) use ($link): ?string {
    if (!$link) return null;
    $url = $link;
    foreach ($row as $key => $value) {
        $url = str_replace('{' . $key . '}', $value, $url);
    }
    return $url;
};
@endphp

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full">
            <div class="overflow-hidden border border-base-300 rounded-lg relative">
                {{-- Subtle loading bar on sort --}}
                <div wire:loading wire:target="sortBy" class="absolute top-0 inset-x-0 h-0.5 bg-primary/50 z-10">
                    <div class="h-full bg-primary animate-pulse w-1/3"></div>
                </div>
                <table class="min-w-full divide-y divide-base-300">
                    <thead class="bg-base-200">
                        <tr>
                            @foreach($headers as $header)
                                @php $isActive = ($sortBy['column'] ?? '') === ($header['key'] ?? ''); @endphp
                                <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider {{ $isActive ? 'text-base-content' : 'text-base-content/50' }} {{ $header['class'] ?? '' }}">
                                    @if($sortBy && isset($header['key']) && $header['key'] !== 'actions' && ($header['sortable'] ?? true) !== false)
                                        <button
                                            wire:click="$wire.set('sortBy', {column: '{{ $header['key'] }}', direction: '{{ $isActive && ($sortBy['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc' }}'})"
                                            class="inline-flex items-center gap-1.5 hover:text-base-content transition-colors group"
                                            aria-sort="{{ $isActive ? (($sortBy['direction'] ?? 'asc') === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                        >
                                            <span class="{{ $isActive ? 'text-primary' : '' }}">{{ $header['label'] }}</span>
                                            <span class="{{ $isActive ? 'text-primary' : 'text-base-content/20' }} group-hover:text-base-content/50 transition-colors">
                                                @if($isActive)
                                                    @if(($sortBy['direction'] ?? 'asc') === 'asc')
                                                        <x-icon name="o-chevron-up" class="w-3.5 h-3.5" />
                                                    @else
                                                        <x-icon name="o-chevron-down" class="w-3.5 h-3.5" />
                                                    @endif
                                                @else
                                                    <x-icon name="o-chevron-up-down" class="w-3.5 h-3.5" />
                                                @endif
                                            </span>
                                        </button>
                                    @else
                                        {{ $header['label'] }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-base-300 bg-white">
                        @forelse($rows as $i => $row)
                            @include('components.table-row', ['headers' => $headers, 'row' => $row, 'link' => $link, 'index' => $i])
                        @empty
                            <tr>
                                <td colspan="{{ count($headers) }}" class="px-5 py-8 text-center">
                                    @if(isset($empty))
                                        {{ $empty }}
                                    @else
                                        <div class="flex flex-col items-center gap-2 py-4 text-base-content/40">
                                            <x-icon name="o-inbox" class="w-8 h-8" />
                                            <p class="text-sm">{{ __('app.common.empty_table') }}</p>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($withPagination && method_exists($rows, 'links'))
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    @endif
</div>
