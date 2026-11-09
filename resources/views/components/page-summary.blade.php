@props([
    'eyebrow' => null,
    'title',
    'titleId' => null,
    'metricsClass' => 'grid grid-cols-2 gap-px bg-border lg:grid-cols-4',
])

<section
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border bg-border']) }}
    @if($titleId) aria-labelledby="{{ $titleId }}" @endif
>
    <div class="flex flex-col gap-4 bg-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div>
            @if($eyebrow)
                <p class="text-xs font-medium text-content-muted">{{ $eyebrow }}</p>
            @endif
            <h2
                @if($titleId) id="{{ $titleId }}" @endif
                @class(['text-base font-semibold text-content', 'mt-1' => filled($eyebrow)])
            >{{ $title }}</h2>
        </div>
        @isset($actions)
            <div class="flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    <dl class="{{ $metricsClass }}">
        {{ $slot }}
    </dl>

    @isset($footer)
        {{ $footer }}
    @endisset
</section>
