@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="flex items-center justify-between gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-10 items-center rounded-lg border border-border bg-surface-muted px-3 text-sm font-medium text-content-muted" aria-disabled="true">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-10 items-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-10 items-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex h-10 items-center rounded-lg border border-border bg-surface-muted px-3 text-sm font-medium text-content-muted" aria-disabled="true">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden items-center justify-between gap-4 sm:flex">
            <p class="text-sm text-content-muted">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-medium text-content">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-medium text-content">{{ $paginator->lastItem() }}</span>
                @else
                    <span class="font-medium text-content">{{ $paginator->count() }}</span>
                @endif
                {!! __('of') !!}
                <span class="font-medium text-content">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="inline-flex overflow-hidden rounded-lg border border-border bg-white">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex size-10 items-center justify-center text-content-muted" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M11.707 14.707a1 1 0 01-1.414 0L6.586 11a1 1 0 010-1.414l3.707-3.707a1 1 0 011.414 1.414L8.707 10l3 3a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex size-10 items-center justify-center text-content-muted transition hover:bg-surface-muted hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="{{ __('pagination.previous') }}">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M11.707 14.707a1 1 0 01-1.414 0L6.586 11a1 1 0 010-1.414l3.707-3.707a1 1 0 011.414 1.414L8.707 10l3 3a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="inline-flex size-10 items-center justify-center border-l border-border text-sm text-content-muted" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="inline-flex size-10 items-center justify-center border-l border-border bg-primary-subtle text-sm font-medium text-primary" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex size-10 items-center justify-center border-l border-border text-sm font-medium text-content transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex size-10 items-center justify-center border-l border-border text-content-muted transition hover:bg-surface-muted hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label="{{ __('pagination.next') }}">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l3.707 3.707a1 1 0 010 1.414l-3.707 3.707a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                    </a>
                @else
                    <span class="inline-flex size-10 items-center justify-center border-l border-border text-content-muted" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l3.707 3.707a1 1 0 010 1.414l-3.707 3.707a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif