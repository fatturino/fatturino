{{-- Custom Fatturino pagination — borderless pill style for Livewire --}}
@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">

            {{-- Mobile: simple prev/next --}}
            <div class="flex justify-between flex-1 sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <span class="text-sm text-base-content/30 px-3 py-1.5">{!! __('pagination.previous') !!}</span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="text-sm text-base-content/70 hover:text-primary px-3 py-1.5 rounded-full hover:bg-primary/8 transition cursor-pointer">
                            {!! __('pagination.previous') !!}
                        </button>
                    @endif
                </span>
                <span>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="text-sm text-base-content/70 hover:text-primary px-3 py-1.5 rounded-full hover:bg-primary/8 transition cursor-pointer">
                            {!! __('pagination.next') !!}
                        </button>
                    @else
                        <span class="text-sm text-base-content/30 px-3 py-1.5">{!! __('pagination.next') !!}</span>
                    @endif
                </span>
            </div>

            {{-- Desktop: full pagination --}}
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">

                {{-- Results count --}}
                <div>
                    <p class="text-sm text-base-content/50">
                        <span>{!! __('Showing') !!}</span>
                        <span class="font-medium text-base-content/70">{{ $paginator->firstItem() }}</span>
                        <span>{!! __('to') !!}</span>
                        <span class="font-medium text-base-content/70">{{ $paginator->lastItem() }}</span>
                        <span>{!! __('of') !!}</span>
                        <span class="font-medium text-base-content/70">{{ $paginator->total() }}</span>
                        <span>{!! __('results') !!}</span>
                    </p>
                </div>

                {{-- Page buttons --}}
                <div>
                    <span class="inline-flex items-center gap-0.5">

                        {{-- Previous arrow --}}
                        <span>
                            @if ($paginator->onFirstPage())
                                <span class="w-8 h-8 inline-flex items-center justify-center rounded-full text-base-content/20" aria-disabled="true" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                </span>
                            @else
                                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="w-8 h-8 inline-flex items-center justify-center rounded-full text-base-content/60 hover:text-primary hover:bg-primary/8 transition cursor-pointer" aria-label="{{ __('pagination.previous') }}">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                </button>
                            @endif
                        </span>

                        {{-- Page numbers --}}
                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <span class="w-8 h-8 inline-flex items-center justify-center text-sm text-base-content/30" aria-disabled="true">{{ $element }}</span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page" class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-primary text-primary-content text-sm font-semibold">{{ $page }}</span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="w-8 h-8 inline-flex items-center justify-center rounded-full text-sm text-base-content/60 hover:text-primary hover:bg-primary/8 transition cursor-pointer" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        {{-- Next arrow --}}
                        <span>
                            @if ($paginator->hasMorePages())
                                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="w-8 h-8 inline-flex items-center justify-center rounded-full text-base-content/60 hover:text-primary hover:bg-primary/8 transition cursor-pointer" aria-label="{{ __('pagination.next') }}">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                                </button>
                            @else
                                <span class="w-8 h-8 inline-flex items-center justify-center rounded-full text-base-content/20" aria-disabled="true" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                                </span>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
