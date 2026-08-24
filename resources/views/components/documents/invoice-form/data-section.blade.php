@props(['title' => 'Dati documento', 'variant' => 'default'])

@if($variant === 'editor')
    <section {{ $attributes->merge(['class' => '']) }}>
        {{ $slot }}
    </section>
@else
    <article {{ $attributes->merge(['class' => 'rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]']) }}>
        <h2 class="mb-5 text-base font-bold text-content">{{ $title }}</h2>
        {{ $slot }}
    </article>
@endif
