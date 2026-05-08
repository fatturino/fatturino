<div class="px-3 py-2 mary-hideable">
    <div class="flex items-center gap-2">
        @if($selectedYear < $currentYear)
            <x-icon name="o-lock-closed" class="w-4 h-4 text-white/60 shrink-0" />
        @else
            <x-icon name="o-calendar" class="w-4 h-4 text-white/60 shrink-0" />
        @endif

        <select
            wire:model.live="selectedYear"
            class="w-full bg-white/10 border border-white/15 rounded-md px-2 py-1.5 text-sm text-white font-semibold focus:outline-none focus:ring-1 focus:ring-white/30"
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
        <p class="text-xs text-white/50 mt-1 ml-6">
            Anno fiscale concluso
        </p>
    @endif
</div>
