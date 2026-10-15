@props([
    'submission' => null,
    'fallbackLabel',
    'fallbackVariant',
])

@if($submission)
    <div class="space-y-1">
        <x-badge value="Invio da verificare" variant="warning" />
        <p class="text-xs font-medium text-warning">Non reinviare</p>
    </div>
@else
    <x-badge :value="$fallbackLabel" :variant="$fallbackVariant" />
@endif
