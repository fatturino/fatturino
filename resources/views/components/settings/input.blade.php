@props(['label', 'type' => 'text'])
<label class="block text-sm font-semibold text-content">{{ $label }}<input {{ $attributes->merge(['class' => 'mt-2 block w-full rounded-md border border-border px-3 py-2']) }} type="{{ $type }}">@error($attributes->wire('model')->value())<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror</label>
