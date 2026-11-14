@props(['paginator', 'noun' => 'items'])
@if ($paginator->total() > 0)
    <nav class="pagination-bar" aria-label="Pagination">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $noun }}</span>

        @if ($paginator->hasPages())
            <div class="pages">
                <a class="page-link-item" href="{{ $paginator->previousPageUrl() }}" rel="prev" @if ($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>Previous</a>

                @foreach ($paginator->onEachSide(1)->linkCollection()->slice(1, -1) as $link)
                    @if ($link['url'] === null)
                        <span class="page-link-item" aria-hidden="true">…</span>
                    @else
                        <a class="page-link-item" href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif aria-label="Page {{ $link['label'] }}">{{ $link['label'] }}</a>
                    @endif
                @endforeach

                <a class="page-link-item" href="{{ $paginator->nextPageUrl() }}" rel="next" @if (! $paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endif>Next</a>
            </div>
        @endif
    </nav>
@endif
