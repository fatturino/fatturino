@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('datePicker', (config = {}) => ({
                value: '',
                start: '',
                end: '',
                range: false,
                min: null,
                max: null,
                disabled: false,
                required: false,
                inputId: '',
                placeholder: 'gg/mm/aaaa',
                describedBy: '',
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
