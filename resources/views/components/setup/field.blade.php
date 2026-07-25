@props(['label', 'field', 'type' => 'text', 'autocomplete' => null, 'maxlength' => null])
<label class="block text-sm font-semibold text-content">{{ $label }}
    <input wire:model="{{ $field }}" type="{{ $type }}" @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if($maxlength) maxlength="{{ $maxlength }}" @endif class="mt-2 block w-full rounded-md border border-border px-4 py-3 text-sm focus:border-primary focus:ring-3 focus:ring-primary/15" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">
    @error($field)<span class="mt-1 block text-xs text-danger" role="alert">{{ $message }}</span>@enderror
</label>
