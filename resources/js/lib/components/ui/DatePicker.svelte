<script lang="ts">
    import { DatePicker as DatePickerPrimitive } from "bits-ui";
    import { parseDate, type CalendarDate } from "@internationalized/date";
    import CalendarBlank from "phosphor-svelte/lib/CalendarBlank";
    import { formatLocalDate, normalizeDateOnlyString } from "$lib/utils/date.js";

    let {
        value = $bindable(""),
        disabled = false,
        placeholder = "Seleziona data",
        class: className = "",
        locale = "it-IT",
    } = $props();

    function toDateValue(iso: string) {
        const normalized = normalizeDateOnlyString(iso);
        if (!normalized) return undefined;
        return parseDate(normalized);
    }

    function toIsoDate(dateValue?: CalendarDate) {
        if (!dateValue) return "";
        const month = String(dateValue.month).padStart(2, "0");
        const day = String(dateValue.day).padStart(2, "0");
        return `${dateValue.year}-${month}-${day}`;
    }

    function isSameDateValue(left?: CalendarDate, right?: CalendarDate) {
        if (!left && !right) return true;
        if (!left || !right) return false;

        return left.year === right.year && left.month === right.month && left.day === right.day;
    }

    let selectedDateValue = $state<CalendarDate | undefined>(toDateValue(value));
    let normalizedValue = $derived(normalizeDateOnlyString(value));

    $effect(() => {
        const nextValue = toDateValue(value);
        if (!isSameDateValue(selectedDateValue, nextValue)) {
            selectedDateValue = nextValue;
        }
    });
</script>

<DatePickerPrimitive.Root
    type="single"
    value={selectedDateValue}
    onValueChange={(nextValue) => {
        selectedDateValue = nextValue;
        value = toIsoDate(nextValue);
    }}
    {disabled}
    {locale}
    weekStartsOn={1}
>
    <DatePickerPrimitive.Trigger class={`mt-1 flex w-full items-center justify-between rounded-lg border border-brand-secondary/20 bg-white px-3 py-2 text-sm form-focus ${className}`}>
        {#if normalizedValue}
            {formatLocalDate(normalizedValue, locale)}
        {:else}
            <span class="text-brand-secondary/50">{placeholder}</span>
        {/if}
        <CalendarBlank class="size-4 text-brand-secondary/60" />
    </DatePickerPrimitive.Trigger>
    <DatePickerPrimitive.Portal>
        <DatePickerPrimitive.Content class="z-50 mt-1 rounded-lg border border-brand-secondary/20 bg-white p-3 shadow-lg">
            <DatePickerPrimitive.Calendar>
                {#snippet children({ months, weekdays })}
                    <DatePickerPrimitive.Header class="mb-2 flex items-center justify-between">
                        <DatePickerPrimitive.PrevButton class="rounded p-1 hover:bg-brand-secondary/5">‹</DatePickerPrimitive.PrevButton>
                        <DatePickerPrimitive.Heading class="text-sm font-semibold text-brand-deep" />
                        <DatePickerPrimitive.NextButton class="rounded p-1 hover:bg-brand-secondary/5">›</DatePickerPrimitive.NextButton>
                    </DatePickerPrimitive.Header>
                    {#each months as month}
                        <DatePickerPrimitive.Grid>
                            <DatePickerPrimitive.GridHead>
                                <DatePickerPrimitive.GridRow>
                                    {#each weekdays as day}
                                        <DatePickerPrimitive.HeadCell class="w-9 pb-1 text-center text-xs text-brand-secondary/60">{day}</DatePickerPrimitive.HeadCell>
                                    {/each}
                                </DatePickerPrimitive.GridRow>
                            </DatePickerPrimitive.GridHead>
                            <DatePickerPrimitive.GridBody>
                                {#each month.weeks as weekDates}
                                    <DatePickerPrimitive.GridRow>
                                        {#each weekDates as date}
                                            <DatePickerPrimitive.Cell {date} month={month.value}>
                                                <DatePickerPrimitive.Day
                                                    class="inline-flex size-9 items-center justify-center rounded text-sm outline-none hover:bg-brand-secondary/5 data-[selected]:bg-brand data-[selected]:text-white data-[disabled]:opacity-40"
                                                />
                                            </DatePickerPrimitive.Cell>
                                        {/each}
                                    </DatePickerPrimitive.GridRow>
                                {/each}
                            </DatePickerPrimitive.GridBody>
                        </DatePickerPrimitive.Grid>
                    {/each}
                {/snippet}
            </DatePickerPrimitive.Calendar>
        </DatePickerPrimitive.Content>
    </DatePickerPrimitive.Portal>
</DatePickerPrimitive.Root>
