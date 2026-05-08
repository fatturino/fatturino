@props([
    'wireSubmit' => null,
])

<form
    @if($wireSubmit) wire:submit="{{ $wireSubmit }}" @endif
    {{ $attributes->merge(['class' => 'space-y-5']) }}
>
    {{ $slot }}

    @if(isset($actions))
        <div class="flex items-center gap-3 pt-2">
            {{ $actions }}
        </div>
    @endif
</form>
