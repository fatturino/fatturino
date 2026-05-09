@props([
    'label' => null,
    'icon' => null,
    'placeholder' => null,
    'hint' => null,
    'inline' => false,
])

@php
$modelName = null;
try { $modelName = $attributes->wire('model')->value(); } catch (\Throwable) {}

$wrapperClasses = $inline ? 'flex items-center gap-3' : 'w-full';
$inputClasses = 'flex w-full h-11 px-3 py-2 text-sm bg-white border rounded-md border-base-300 placeholder:text-base-content/40 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer';

if ($icon) {
    $inputClasses .= ' pl-10';
}

$error = null;
try { $error = $errors->first($modelName); } catch (\Throwable) {}
if ($error) {
    $inputClasses .= ' border-error focus:ring-error/50';
}
@endphp

<div class="{{ $wrapperClasses }}"
     x-data="{
        open: false,
        value: @entangle($attributes->wire('model')),
        month: new Date().getMonth(),
        year: new Date().getFullYear(),
        monthNames: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
        blankDays: [],
        daysInMonth: [],

        init() {
            const d = this.value ? new Date(this.value) : new Date();
            this.month = d.getMonth();
            this.year = d.getFullYear();
            this.calcDays();
        },

        select(day) {
            const d = new Date(this.year, this.month, day);
            this.value = d.toISOString().split('T')[0];
            this.open = false;
        },

        formatDisplay() {
            if (!this.value) return '';
            const d = new Date(this.value + 'T00:00:00');
            return d.toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        prevMonth() {
            if (this.month === 0) { this.month = 11; this.year--; }
            else { this.month--; }
            this.calcDays();
        },

        nextMonth() {
            if (this.month === 11) { this.month = 0; this.year++; }
            else { this.month++; }
            this.calcDays();
        },

        isToday(day) {
            const t = new Date();
            return t.getDate() === day && t.getMonth() === this.month && t.getFullYear() === this.year;
        },

        isSelected(day) {
            if (!this.value) return false;
            const d = new Date(this.value + 'T00:00:00');
            return d.getDate() === day && d.getMonth() === this.month && d.getFullYear() === this.year;
        },

        calcDays() {
            const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
            const dayOfWeek = new Date(this.year, this.month).getDay(); // 0=Sun, 1=Mon, ...
            this.blankDays = Array.from({length: dayOfWeek === 0 ? 6 : dayOfWeek - 1}, (_, i) => i);
            this.daysInMonth = Array.from({length: daysInMonth}, (_, i) => i + 1);
        }
     }"
     x-init="init()"
     @click.away="open = false"
     class="relative"
>
    @if($label)
        <label class="text-sm font-medium text-base-content/70 mb-1 block">{{ $label }}</label>
    @endif

    <div class="relative">
        @if($icon)
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-base-content/40">
                <x-icon :name="$icon" class="w-4 h-4" />
            </span>
        @endif

        <input
            type="text"
            readonly
            :value="formatDisplay()"
            @click="open = !open"
            placeholder="{{ $placeholder ?? 'GG/MM/AAAA' }}"
            class="{{ $inputClasses }}"
        />

        <span @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-base-content/40 hover:text-base-content">
            <x-icon name="o-calendar" class="w-4 h-4" />
        </span>

        {{-- Datepicker dropdown --}}
        <div x-show="open" x-transition x-cloak
             class="absolute top-full left-0 mt-1 z-50 p-3 bg-white border border-base-300 rounded-lg shadow-lg w-64">
            <div class="flex items-center justify-between mb-2">
                <button @click="prevMonth()" type="button" class="p-1 hover:bg-base-200 rounded">
                    <x-icon name="o-chevron-left" class="w-4 h-4" />
                </button>
                <span class="text-sm font-semibold" x-text="monthNames[month] + ' ' + year"></span>
                <button @click="nextMonth()" type="button" class="p-1 hover:bg-base-200 rounded">
                    <x-icon name="o-chevron-right" class="w-4 h-4" />
                </button>
            </div>

            {{-- Day names --}}
            <div class="grid grid-cols-7 gap-0.5 mb-1">
                <template x-for="d in ['L', 'M', 'M', 'G', 'V', 'S', 'D']">
                    <div class="text-center text-xs font-medium text-base-content/40 py-1" x-text="d"></div>
                </template>
            </div>

            {{-- Days grid --}}
            <div class="grid grid-cols-7 gap-0.5">
                <template x-for="b in blankDays">
                    <div></div>
                </template>
                <template x-for="day in daysInMonth">
                    <div @click="select(day)"
                         :class="{
                            'bg-primary text-primary-content rounded-full': isSelected(day),
                            'bg-base-200 rounded-full': isToday(day) && !isSelected(day),
                            'hover:bg-base-200 rounded-full cursor-pointer': !isSelected(day)
                         }"
                         class="text-center text-sm py-1 transition-colors"
                         x-text="day"></div>
                </template>
            </div>
        </div>
    </div>

    @if($error)
        <p class="text-error text-xs mt-1">{{ $error }}</p>
    @elseif($hint)
        <p class="text-base-content/40 text-xs mt-1">{{ $hint }}</p>
    @endif
</div>
