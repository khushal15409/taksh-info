{{-- <x-pagination :paginator="$orders" /> - previous / next with the page numbers around the current one; renders nothing for a single page. --}}
@props(['paginator'])
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect(range(max(1, $current - 2), min($last, $current + 2)));
        $link = 'px-3 py-1.5 rounded-xl text-xs font-bold border ';
    @endphp
    <nav role="navigation" aria-label="Pagination" {{ $attributes->merge(['class' => 'flex items-center justify-center gap-2 flex-wrap']) }}>
        @if ($paginator->onFirstPage())
            <span class="{{ $link }}border-gray-100 text-gray-300">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}border-gray-200 text-gray-700 hover:border-brand-500">Previous</a>
        @endif
        @if ($pages->first() > 1)
            <a href="{{ $paginator->url(1) }}" class="{{ $link }}border-gray-200 text-gray-700 hover:border-brand-500">1</a>
            @if ($pages->first() > 2)<span class="text-gray-400">…</span>@endif
        @endif
        @foreach ($pages as $page)
            @if ($page === $current)
                <span aria-current="page" class="{{ $link }}border-brand-500 bg-brand-500 text-white">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" class="{{ $link }}border-gray-200 text-gray-700 hover:border-brand-500">{{ $page }}</a>
            @endif
        @endforeach
        @if ($pages->last() < $last)
            @if ($pages->last() < $last - 1)<span class="text-gray-400">…</span>@endif
            <a href="{{ $paginator->url($last) }}" class="{{ $link }}border-gray-200 text-gray-700 hover:border-brand-500">{{ $last }}</a>
        @endif
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}border-gray-200 text-gray-700 hover:border-brand-500">Next</a>
        @else
            <span class="{{ $link }}border-gray-100 text-gray-300">Next</span>
        @endif
    </nav>
@endif
