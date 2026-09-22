@props([
    'label' => null,
    'hint' => null,
    'placeholder' => 'gg/mm/aaaa',
    'required' => false,
    'disabled' => false,
    'min' => null,
    'max' => null,
    'range' => false,
    'startWireModel' => null,
    'endWireModel' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $wireModelName = $wireModel?->value();
    $errorKey = $wireModelName ?? $startWireModel;
    $error = $errorKey ? ($errors ?? new \Illuminate\Support\ViewErrorBag)->first($errorKey) : null;
    $id = 'date-picker-'.str()->uuid();
    $describedBy = collect([$hint ? "{$id}-hint" : null, $error ? "{$id}-error" : null])->filter()->implode(' ');
@endphp

<div
    x-data="datePicker({
        value: @if($wireModelName) @entangle($wireModelName) @else @js($attributes->get('value', '')) @endif,
        start: @js(old($startWireModel, '')),
        end: @js(old($endWireModel, '')),
        range: @js($range),
        min: @js($min),
        max: @js($max),
        disabled: @js($disabled),
        required: @js($required),
        inputId: @js($id),
        placeholder: @js($placeholder),
        describedBy: @js($describedBy),
    })"
    x-init="init()"
    x-modelable="value"
    {{ $attributes->whereStartsWith('x-model') }}
    class="date-picker"
    @keydown.escape.prevent.stop="close(true)"
>
    @if($label)
        <label :for="inputId" class="mb-1 block text-sm font-semibold text-content">{{ $label }}@if($required) <span aria-hidden="true">*</span><span class="sr-only"> obbligatorio</span>@endif</label>
    @endif

    @if($range)
        <input type="hidden" x-ref="startModel" @if($startWireModel) wire:model="{{ $startWireModel }}" @endif x-model="start">
        <input type="hidden" x-ref="endModel" @if($endWireModel) wire:model="{{ $endWireModel }}" @endif x-model="end">
    @else
        <input type="hidden" x-ref="model" {{ $attributes->whereStartsWith('wire:model') }} x-model="value">
    @endif

    <div class="relative">
        <button
            :id="inputId"
            x-ref="trigger"
            type="button"
            class="date-picker__trigger"
            :class="{ 'date-picker__trigger--error': hasError, 'date-picker__trigger--empty': !displayValue }"
            :disabled="disabled"
            :aria-expanded="open.toString()"
            aria-haspopup="dialog"
            :aria-controls="`${inputId}-dialog`"
            :aria-describedby="describedBy || null"
            @click="toggle()"
            @keydown.arrow-down.prevent="openCalendar()"
            @keydown.enter.prevent="toggle()"
            @keydown.space.prevent="toggle()"
        >
            <span x-text="displayValue || placeholder"></span>
            <x-icon name="o-calendar" class="size-5 shrink-0 text-content-muted" aria-hidden="true" />
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            x-ref="dialog"
            :id="`${inputId}-dialog`"
            class="date-picker__dialog"
            role="dialog"
            aria-modal="false"
            :aria-label="range ? 'Seleziona un intervallo di date' : 'Seleziona una data'"
            @click.outside="close()"
        >
            <div class="date-picker__header">
                <button type="button" class="date-picker__nav" @click="previousMonth()" aria-label="Mese precedente"><x-icon name="o-chevron-left" class="size-5" /></button>
                <p class="text-sm font-semibold capitalize text-content" aria-live="polite" x-text="monthLabel"></p>
                <button type="button" class="date-picker__nav" @click="nextMonth()" aria-label="Mese successivo"><x-icon name="o-chevron-right" class="size-5" /></button>
            </div>

            <div class="date-picker__weekdays" aria-hidden="true">
                <template x-for="(weekday, index) in weekdays" :key="index"><span x-text="weekday"></span></template>
            </div>

            <div class="date-picker__grid" role="grid" :aria-label="monthLabel">
                <template x-for="day in days" :key="day.iso">
                    <button
                        type="button"
                        role="gridcell"
                        class="date-picker__day"
                        :class="{
                            'date-picker__day--outside': !day.currentMonth,
                            'date-picker__day--today': day.today,
                            'date-picker__day--selected': day.selected,
                            'date-picker__day--range': day.inRange,
                            'date-picker__day--range-edge': day.rangeEdge,
                        }"
                        :disabled="day.disabled"
                        :aria-label="day.label"
                        :aria-selected="day.selected || day.inRange"
                        :tabindex="day.focused ? 0 : -1"
                        @click="select(day.iso)"
                        @keydown="onDayKeydown($event, day.iso)"
                        x-text="day.number"
                    ></button>
                </template>
            </div>

            <div class="date-picker__footer">
                <button type="button" class="btn-ghost px-3 py-2 text-sm" @click="select(todayIso)">Oggi</button>
                <button type="button" class="btn-ghost px-3 py-2 text-sm" @click="clear()" :disabled="!hasValue">Cancella</button>
            </div>
        </div>
    </div>

    @if($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-xs text-content-muted">{{ $hint }}</p>
    @endif
    @if($error)
        <p id="{{ $id }}-error" class="mt-1 text-xs text-danger">{{ $error }}</p>
    @endif
</div>

@once
    <style>
        [x-cloak] { display: none !important; }
        .date-picker__trigger { display: flex; min-height: 2.75rem; width: 100%; align-items: center; justify-content: space-between; gap: .75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); padding: .5rem .75rem; color: var(--color-text-primary); font-size: .875rem; text-align: left; transition: border-color var(--duration-fast) var(--ease-out), box-shadow var(--duration-fast) var(--ease-out); }
        .date-picker__trigger:hover:not(:disabled) { border-color: var(--color-border-strong); }
        .date-picker__trigger--empty { color: var(--color-text-muted); }
        .date-picker__trigger--error { border-color: var(--color-danger); }
        .date-picker__trigger:disabled { cursor: not-allowed; background: var(--color-surface-muted); opacity: .65; }
        .date-picker__dialog { position: absolute; z-index: 50; width: min(21rem, calc(100vw - 2rem)); margin-top: .5rem; overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-xl); background: var(--color-surface); box-shadow: var(--shadow-elevated); }
        .date-picker__header { display: grid; grid-template-columns: 2.75rem minmax(0, 1fr) 2.75rem; align-items: center; padding: .625rem; border-bottom: 1px solid var(--color-border); text-align: center; }
        .date-picker__nav { display: inline-flex; min-height: 2.75rem; min-width: 2.75rem; align-items: center; justify-content: center; border-radius: var(--radius-md); color: var(--color-text-secondary); }
        .date-picker__nav:hover { background: var(--color-surface-muted); color: var(--color-text-primary); }
        .date-picker__weekdays, .date-picker__grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .date-picker__weekdays { padding: .5rem .625rem 0; color: var(--color-text-muted); font-size: .75rem; font-weight: 600; text-align: center; }
        .date-picker__grid { gap: .125rem; padding: .5rem .625rem .625rem; }
        .date-picker__day { position: relative; display: inline-flex; min-height: 2.5rem; align-items: center; justify-content: center; border-radius: var(--radius-md); color: var(--color-text-primary); font-size: .875rem; font-variant-numeric: tabular-nums; }
        .date-picker__day:hover:not(:disabled) { background: var(--color-primary-subtle); color: var(--color-primary); }
        .date-picker__day--outside { color: var(--color-text-muted); opacity: .65; }
        .date-picker__day--today { box-shadow: inset 0 0 0 1px var(--color-primary); }
        .date-picker__day--selected, .date-picker__day--range-edge { background: var(--color-primary); color: white; }
        .date-picker__day--selected:hover:not(:disabled), .date-picker__day--range-edge:hover:not(:disabled) { background: var(--color-primary-hover); color: white; }
        .date-picker__day--range { border-radius: 0; background: var(--color-primary-subtle); color: var(--color-primary); }
        .date-picker__day:disabled { cursor: not-allowed; color: var(--color-text-muted); opacity: .35; }
        .date-picker__footer { display: flex; justify-content: space-between; border-top: 1px solid var(--color-border); padding: .375rem .5rem; }
        @media (max-width: 639px) { .date-picker__dialog { position: fixed; right: 1rem; bottom: max(1rem, env(safe-area-inset-bottom)); left: 1rem; width: auto; margin: 0; } }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('datePicker', (config) => ({
                ...config,
                open: false,
                focusedIso: null,
                viewDate: new Date(),
                weekdays: ['L', 'M', 'M', 'G', 'V', 'S', 'D'],
                hasError: false,
                init() {
                    this.hasError = this.$el.querySelector('[id$="-error"]') !== null;
                    const initial = this.range ? (this.start || this.end) : this.value;
                    this.viewDate = this.parse(initial) || new Date();
                    this.focusedIso = initial || this.todayIso;
                    this.$watch('value', () => this.syncModel());
                    this.$watch('start', () => this.syncModel());
                    this.$watch('end', () => this.syncModel());
                },
                get todayIso() { return this.toIso(new Date()); },
                get hasValue() { return this.range ? Boolean(this.start || this.end) : Boolean(this.value); },
                get displayValue() { return this.range ? [this.format(this.start), this.format(this.end)].filter(Boolean).join(' – ') : this.format(this.value); },
                get monthLabel() { return new Intl.DateTimeFormat('it-IT', { month: 'long', year: 'numeric' }).format(this.viewDate); },
                get days() {
                    const first = new Date(this.viewDate.getFullYear(), this.viewDate.getMonth(), 1);
                    const offset = (first.getDay() + 6) % 7;
                    const start = new Date(first); start.setDate(first.getDate() - offset);
                    return Array.from({ length: 42 }, (_, index) => {
                        const date = new Date(start); date.setDate(start.getDate() + index);
                        const iso = this.toIso(date); const selected = this.range ? iso === this.start || iso === this.end : iso === this.value;
                        const inRange = this.range && this.start && this.end && iso > this.start && iso < this.end;
                        return { iso, number: date.getDate(), currentMonth: date.getMonth() === this.viewDate.getMonth(), today: iso === this.todayIso, selected, inRange, rangeEdge: selected, disabled: this.isDisabled(iso), focused: iso === this.focusedIso, label: new Intl.DateTimeFormat('it-IT', { dateStyle: 'full' }).format(date) };
                    });
                },
                toggle() { this.open ? this.close() : this.openCalendar(); },
                openCalendar() { if (this.disabled) return; this.open = true; this.$nextTick(() => requestAnimationFrame(() => this.focusDay())); },
                close(returnFocus = false) { this.open = false; if (returnFocus) this.$nextTick(() => this.$refs.trigger.focus()); },
                previousMonth() { this.moveMonth(-1); },
                nextMonth() { this.moveMonth(1); },
                moveMonth(amount) { this.viewDate = new Date(this.viewDate.getFullYear(), this.viewDate.getMonth() + amount, 1); },
                select(iso) {
                    if (this.isDisabled(iso)) return;
                    if (!this.range) { this.value = iso; this.focusedIso = iso; this.close(true); return; }
                    if (!this.start || (this.start && this.end) || iso < this.start) { this.start = iso; this.end = ''; } else { this.end = iso; this.close(true); }
                    this.focusedIso = iso;
                },
                clear() { this.value = ''; this.start = ''; this.end = ''; },
                onDayKeydown(event, iso) {
                    const keys = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
                    if (keys[event.key]) { event.preventDefault(); this.moveFocus(iso, keys[event.key]); }
                    if (event.key === 'Home') { event.preventDefault(); this.moveFocus(iso, -((this.parse(iso).getDay() + 6) % 7)); }
                    if (event.key === 'End') { event.preventDefault(); this.moveFocus(iso, 6 - ((this.parse(iso).getDay() + 6) % 7)); }
                    if (event.key === 'PageUp') { event.preventDefault(); this.moveMonth(event.shiftKey ? -12 : -1); this.focusDay(); }
                    if (event.key === 'PageDown') { event.preventDefault(); this.moveMonth(event.shiftKey ? 12 : 1); this.focusDay(); }
                    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.select(iso); }
                },
                moveFocus(iso, amount) { const date = this.parse(iso); date.setDate(date.getDate() + amount); this.focusedIso = this.toIso(date); this.viewDate = new Date(date.getFullYear(), date.getMonth(), 1); this.$nextTick(() => this.focusDay()); },
                focusDay() { this.$refs.dialog?.querySelector('[role="gridcell"][tabindex="0"]')?.focus(); },
                isDisabled(iso) { return Boolean((this.min && iso < this.min) || (this.max && iso > this.max)); },
                syncModel() { this.$nextTick(() => { const elements = this.range ? [this.$refs.startModel, this.$refs.endModel] : [this.$refs.model]; elements.forEach((element) => element?.dispatchEvent(new Event('input', { bubbles: true }))); }); },
                parse(iso) { if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null; const [year, month, day] = iso.split('-').map(Number); return new Date(year, month - 1, day); },
                toIso(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; },
                format(iso) { const date = this.parse(iso); return date ? new Intl.DateTimeFormat('it-IT').format(date) : ''; },
            }));
        });
    </script>
@endonce
