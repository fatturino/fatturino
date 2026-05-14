@props(['headers' => [], 'row' => null, 'link' => null, 'index' => 0, 'selectable' => false, 'selectedIds' => []])

@php
$rowId = data_get($row, 'id', $index);

$buildLink = function(array $row) use ($link): ?string {
    if (!$link) return null;
    $url = $link;
    foreach ($row as $key => $value) {
        $url = str_replace('{' . $key . '}', is_scalar($value) ? (string) $value : '', $url);
    }
    return $url;
};
$rowLink = $buildLink(is_array($row) ? $row : $row->toArray());
@endphp

<tr wire:key="row-{{ $rowId }}"
    class="text-base-content transition-colors {{ $rowLink ? 'cursor-pointer hover:bg-base-100' : '' }}"
    @if($rowLink) onclick="window.location='{{ $rowLink }}'" @endif>
    @if($selectable)
        @php
            $isSelected = in_array((string) $rowId, $selectedIds);
        @endphp
        <td class="px-5 py-4" onclick="event.stopPropagation()">
            <input
                            type="checkbox"
                            class="w-4 h-4 text-primary rounded border-base-300 focus:ring-primary cursor-pointer"
                wire:model.live="selectedIds"
                value="{{ $rowId }}"
            />
        </td>
    @endif
    @foreach($headers as $header)
        @php $key = $header['key'] ?? null; @endphp
        <td class="px-5 py-4 text-sm {{ $header['class'] ?? '' }} {{ $key === 'actions' ? 'text-right' : 'whitespace-nowrap' }}">
            @if(isset($header['view']))
                @include($header['view'], ['row' => $row])
            @elseif(isset($header['render']) && is_callable($header['render']))
                {!! ($header['render'])($row) !!}
            @elseif($key && $key !== 'actions')
                @php $cellVal = data_get($row, $key); @endphp
                {{ is_scalar($cellVal) ? $cellVal : (is_null($cellVal) ? '' : json_encode($cellVal)) }}
            @endif
        </td>
    @endforeach
</tr>
