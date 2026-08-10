@props([
    'label' => null,
    'icon' => null,
    'placeholder' => null,
    'hint' => null,
    'options' => [],
    'inline' => false,
    'optionValue' => null,
    'optionLabel' => null,
    'disabled' => false,
    'searchable' => false,
    'searchPlaceholder' => null,
])

@php
$wrapperClasses = $inline ? 'flex items-center gap-3' : 'space-y-1';

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
        $sub = data_get($val, 'subtitle');
        $normalizedOptions[] = [
            'title' => (string) $l,
            'value' => (string) $v,
            'subtitle' => $sub !== null ? (string) $sub : null,
        ];
    } else {
        $normalizedOptions[] = [
            'title' => (string) $val,
            'value' => (string) $key,
            'subtitle' => null,
        ];
    }
}
$optionsJson = json_encode($normalizedOptions);
$placeholderText = $placeholder ?? __('app.common.select');
$searchPlaceholderText = $searchPlaceholder ?? 'Cerca...';
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
                    this.search = '';
                    this.activeIndex = this.filteredOptions.findIndex(o => o.value == this.selectedValue);
                    if (this.activeIndex < 0) this.activeIndex = 0;
                    this.$nextTick(() => {
                        if (this.searchable) this.$refs.searchInput?.focus();
                        this.scrollToActive();
                    });
                }
            });
            this.$watch("search", () => {
                this.activeIndex = this.filteredOptions.length > 0 ? 0 : null;
                this.$nextTick(() => this.scrollToActive());
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
            if (this.activeIndex < this.filteredOptions.length - 1) {
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
            return this.activeIndex !== null && this.filteredOptions[this.activeIndex]?.value === item.value;
        }
    }"
    @keydown.escape="if(open) open = false"
    @keydown.down.prevent="if(open) { activeNext() } else { open = true }"
    @keydown.up.prevent="if(open) { activePrev() } else { open = true }"
    @keydown.enter.prevent="if(open && activeIndex !== null) { select(filteredOptions[activeIndex]) }"
    class="{{ $wrapperClasses }} relative"
>
    @if($label)
        <label class="inline-block font-medium text-sm">{{ $label }}</label>
    @endif

    <div class="relative">
        {{-- Button trigger --}}
        <button
            x-ref="button"
            @click="open = !open"
            @disabled($disabled)
            type="button"
            class="group flex w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm leading-6 focus:border-indigo-500 focus:ring-3 focus:ring-indigo-500/50 focus:outline-none
                   {{ $error ? 'border-danger' : '' }}
                   dark:border-zinc-600 dark:bg-zinc-800 dark:focus:border-indigo-500
                   {{ $icon ? 'pl-10' : '' }}"
            aria-haspopup="listbox"
            aria-controls="tk-select-menu-list"
        >
            @if($icon)
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                    <x-icon :name="$icon" class="w-4 h-4" />
                </span>
            @endif
            <span class="grow truncate" :class="{ 'text-zinc-500 dark:text-zinc-400': !selectedTitle }" x-text="selectedTitle || '{{ $placeholderText }}'"></span>
            <svg
                class="hi-mini hi-chevron-up-down inline-block size-5 flex-none opacity-40 transition group-hover:opacity-60 group-active:scale-90"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        {{-- Dropdown --}}
        <ul
            id="tk-select-menu-list"
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="open = false"
            class="absolute inset-x-0 z-10 mt-2 max-h-60 origin-top overflow-y-auto rounded-lg bg-white py-2.5 shadow-xl ring-1 ring-black/5 focus:outline-none dark:bg-zinc-800 dark:shadow-zinc-900 dark:ring-zinc-700"
            aria-labelledby="tk-select-menu-button"
            aria-orientation="vertical"
            role="listbox"
            tabindex="0"
            x-cloak
        >
                        {{-- Search input --}}
            <div x-show="searchable" class="px-3 pb-2">
                <input
                    x-ref="searchInput"
                    x-model="search"
                    type="search"
                    class="block h-9 w-full rounded-md border border-zinc-200 bg-white px-2.5 py-1 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                    placeholder="{{ $searchPlaceholderText }}"
                    @keydown.down.prevent="activeNext()"
                    @keydown.up.prevent="activePrev()"
                    @keydown.enter.prevent="if(activeIndex !== null) { select(filteredOptions[activeIndex]) }"
                >
            </div>

            <template x-for="item in filteredOptions" :key="item.value">
                <li
                    @click="select(item)"
                    :id="'select-opt-' + item.value"
                    @mousemove="activeIndex = filteredOptions.indexOf(item)"
                    :class="{
                        'font-semibold text-zinc-950 dark:text-white': selectedValue == item.value,
                        'text-zinc-600 dark:text-zinc-300': selectedValue != item.value
                    }"
                    class="group flex cursor-pointer items-center justify-between gap-2 border-y border-transparent px-3 text-sm hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none active:border-indigo-100 dark:hover:bg-zinc-700/75 dark:focus:bg-zinc-700/75 dark:active:border-zinc-600"
                    role="option"
                    tabindex="-1"
                >
                    <div class="grow truncate py-1.5">
                        <div x-text="item.title"></div>
                        <div x-show="item.subtitle" x-text="item.subtitle" class="text-xs text-content-muted"></div>
                    </div>
                    <div
                        :class="{ 'visible text-indigo-600 dark:text-indigo-500': selectedValue == item.value, 'invisible': selectedValue != item.value }"
                        class="pointer-events-none size-5 flex-none"
                        aria-hidden="true"
                    >
                        <svg
                            class="hi-mini hi-check-circle inline-block size-5"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                </li>
            </template>
            {{-- Empty state --}}
            <li x-show="filteredOptions.length === 0" class="px-3 py-2 text-sm text-zinc-400 dark:text-zinc-500">
                Nessun risultato trovato
            </li>
        </ul>
    </div>

    @if($error)
        <p class="text-danger text-xs mt-1">{{ $error }}</p>
    @elseif($hint)
        <p class="text-zinc-400 text-xs mt-1">{{ $hint }}</p>
    @endif
</div>
