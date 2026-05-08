@props([
    'label' => null,
    'icon' => null,
    'placeholder' => null,
    'hint' => null,
    'options' => [],
    'inline' => false,
    'optionValue' => null,
    'optionLabel' => null,
])

@php
$wrapperClasses = $inline ? 'flex items-center gap-3' : 'w-full';

$error = null;
try {
    $modelName = $attributes->wire('model')->value();
    $error = $errors->first($modelName);
} catch (\Throwable) {}

// Normalize options to [{title, value}] format
$normalizedOptions = [];
foreach ($options as $key => $val) {
    if (is_array($val) || is_object($val)) {
        // Try explicit option-value/option-label, then common keys
        $ov = $optionValue ?? 'value';
        $ol = $optionLabel ?? 'title';
        $v = data_get($val, $ov);
        $l = data_get($val, $ol);
        // Fallback: try id/name, then key/label
        if ($v === null) $v = data_get($val, 'id');
        if ($l === null) $l = data_get($val, 'name');
        if ($l === null) $l = data_get($val, 'label');
        // Last resort: first two values
        if ($v === null || $l === null) {
            $arr = is_array($val) ? array_values($val) : array_values((array) $val);
            $v = $v ?? $arr[0] ?? $key;
            $l = $l ?? $arr[1] ?? (string) $val;
        }
        $normalizedOptions[] = [
            'title' => (string) $l,
            'value' => (string) $v,
        ];
    } else {
        $normalizedOptions[] = [
            'title' => (string) $val,
            'value' => (string) $key,
        ];
    }
}
$optionsJson = json_encode($normalizedOptions);
$placeholderText = $placeholder ?? __('app.common.select');
@endphp

<div
    x-data="{
        open: false,
        selectedValue: @entangle($attributes->wire('model')),
        selectedTitle: '',
        activeIndex: null,
        options: {{ $optionsJson }},
        dropdownPosition: 'bottom',

        init() {
            this.updateSelected();
            this.$watch('selectedValue', () => this.updateSelected());
            this.$watch('open', (val) => {
                if (val) {
                    this.activeIndex = this.options.findIndex(o => o.value == this.selectedValue);
                    if (this.activeIndex < 0) this.activeIndex = 0;
                    this.$nextTick(() => this.scrollToActive());
                }
            });
        },

        updateSelected() {
            const found = this.options.find(o => o.value == this.selectedValue);
            this.selectedTitle = found ? found.title : '';
        },

        select(item) {
            this.selectedValue = item.value;
            this.selectedTitle = item.title;
            this.open = false;
            this.$refs.button.focus();
        },

        activeNext() {
            if (this.activeIndex < this.options.length - 1) {
                this.activeIndex++;
                this.scrollToActive();
            }
        },

        activePrev() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
                this.scrollToActive();
            }
        },

        scrollToActive() {
            const el = document.getElementById('select-opt-' + this.options[this.activeIndex]?.value);
            if (el) el.scrollIntoView({ block: 'nearest' });
        },

        isActive(item) {
            return this.activeIndex !== null && this.options[this.activeIndex]?.value === item.value;
        }
    }"
    @keydown.escape="if(open) open = false"
    @keydown.down.prevent="if(open) { activeNext() } else { open = true }"
    @keydown.up.prevent="if(open) { activePrev() } else { open = true }"
    @keydown.enter.prevent="if(open && activeIndex !== null) { select(options[activeIndex]) }"
    class="{{ $wrapperClasses }} relative"
>
    @if($label)
        <label class="text-sm font-medium text-base-content/70 mb-1 block">{{ $label }}</label>
    @endif

    <div class="relative">
        {{-- Button trigger --}}
        <button
            x-ref="button"
            @click="open = !open"
            type="button"
            class="relative flex items-center w-full h-10 px-3 py-2 text-left text-sm bg-white border rounded-md shadow-sm cursor-default
                   {{ $error ? 'border-error' : 'border-base-300' }}
                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50
                   {{ $icon ? 'pl-10' : '' }}"
        >
            @if($icon)
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-base-content/40">
                    <x-icon :name="$icon" class="w-4 h-4" />
                </span>
            @endif
            <span class="block truncate" :class="{ 'text-base-content/40': !selectedTitle }" x-text="selectedTitle || '{{ $placeholderText }}'"></span>
            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                <x-icon name="o-chevron-up-down" class="w-4 h-4 text-base-content/30" />
            </span>
        </button>

        {{-- Dropdown --}}
        <ul
            x-show="open"
            x-transition:enter="transition ease-out duration-50"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click.away="open = false"
            class="absolute z-50 w-full py-1 mt-1 overflow-auto text-sm bg-white rounded-md shadow-lg border border-base-200 max-h-56 focus:outline-none"
            x-cloak
        >
            <template x-for="item in options" :key="item.value">
                <li
                    @click="select(item)"
                    :id="'select-opt-' + item.value"
                    @mousemove="activeIndex = options.indexOf(item)"
                    :class="{
                        'bg-primary/10 text-primary font-medium': isActive(item),
                        'text-base-content': !isActive(item)
                    }"
                    class="relative flex items-center h-full py-2 pl-8 pr-3 cursor-default select-none hover:bg-base-200/50"
                >
                    {{-- Checkmark --}}
                    <span x-show="selectedValue == item.value" class="absolute left-0 ml-2.5 text-primary">
                        <x-icon name="o-check" class="w-4 h-4" />
                    </span>
                    <span class="block truncate" x-text="item.title"></span>
                </li>
            </template>
        </ul>
    </div>

    @if($error)
        <p class="text-error text-xs mt-1">{{ $error }}</p>
    @elseif($hint)
        <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
    @endif
</div>
