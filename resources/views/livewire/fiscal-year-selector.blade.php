<div class="px-3 py-2 mary-hideable">
    <div class="flex items-center gap-2">
        @if($selectedYear < $currentYear)
            <x-icon name="o-lock-closed" class="w-4 h-4 text-warning shrink-0" />
        @else
            <x-icon name="o-calendar" class="w-4 h-4 text-base-content/50 shrink-0" />
        @endif

        <select
            wire:model.live="selectedYear"
            class="select select-sm select-bordered w-full font-semibold"
        >
            @foreach($availableYears as $year)
                <option value="{{ $year }}" @selected($year === $selectedYear)>
                    {{ $year }}
                    @if($year < $currentYear)
                        (solo visualizzazione)
                    @endif
                </option>
            @endforeach
        </select>
    </div>

    @if($selectedYear < $currentYear)
        <p class="text-xs text-warning mt-1 ml-6">
            Anno fiscale concluso
        </p>
    @endif
</div>
