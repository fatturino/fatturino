@props(['title' => 'Dati documento'])

<article class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]">
    <h2 class="mb-5 text-base font-bold text-content">{{ $title }}</h2>
    {{ $slot }}
</article>
