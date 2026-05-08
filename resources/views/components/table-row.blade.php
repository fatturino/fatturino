@props(['headers' => [], 'row' => null, 'link' => null])

@php
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

<tr class="text-base-content transition-colors {{ $rowLink ? 'cursor-pointer hover:bg-base-100' : '' }}"
    @if($rowLink) onclick="window.location='{{ $rowLink }}'" @endif>
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
