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
            <div class="overflow-hidden border border-base-300 rounded-lg">
                <table class="min-w-full divide-y divide-base-300">
                    <thead class="bg-base-200">
                        <tr>
                            @foreach($headers as $header)
                                <th class="px-5 py-3 text-xs font-semibold text-left uppercase tracking-wider text-base-content/60 {{ $header['class'] ?? '' }}">
                                    @if($sortBy && isset($header['key']) && $header['key'] !== 'actions')
                                        <button
                                            wire:click="$set('sortBy', ['column' => '{{ $header['key'] }}', 'direction' => '{{ $sortBy['column'] === $header['key'] && $sortBy['direction'] === 'asc' ? 'desc' : 'asc' }}'])"
                                            class="inline-flex items-center gap-1 hover:text-base-content transition-colors group"
                                        >
                                            <span>{{ $header['label'] }}</span>
                                            <span class="text-base-content/30 group-hover:text-base-content/50">
                                                @if(($sortBy['column'] ?? '') === $header['key'])
                                                    @if(($sortBy['direction'] ?? 'asc') === 'asc')
                                                        &#9650;
                                                    @else
                                                        &#9660;
                                                    @endif
                                                @else
                                                    &#8693;
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
                            @include('components.table-row', ['headers' => $headers, 'row' => $row, 'link' => $link])
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
