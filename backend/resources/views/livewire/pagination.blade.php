{{--
    Numbered pagination for Livewire lists. Each page is a real link, so it
    works without JavaScript and search engines can follow it; Livewire
    takes the click and loads the page in place.
--}}
@php
    $pageName = $paginator->getPageName();
    $item = 'flex size-11 items-center justify-center rounded-full text-sm font-bold';
    $link = $item.' hover:bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $step = 'flex h-11 items-center rounded-full px-4 text-sm font-bold';
@endphp

@if ($paginator->hasPages())
    <nav aria-label="Halaman" class="mt-4 flex flex-wrap items-center justify-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $step }} text-muted" aria-disabled="true">Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:click.prevent="{{ "previousPage('".$pageName."')" }}" x-on:click="window.scrollTo({ top: 0 })" class="{{ $step }} hover:bg-subtle">Sebelumnya</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="{{ $item }} text-muted" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" wire:key="halaman-{{ $page }}" class="{{ $item }} bg-ink text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" wire:key="halaman-{{ $page }}" wire:click.prevent="{{ 'gotoPage('.$page.", '".$pageName."')" }}" x-on:click="window.scrollTo({ top: 0 })" aria-label="Halaman {{ $page }}" class="{{ $link }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:click.prevent="{{ "nextPage('".$pageName."')" }}" x-on:click="window.scrollTo({ top: 0 })" class="{{ $step }} hover:bg-subtle">Berikutnya</a>
        @else
            <span class="{{ $step }} text-muted" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
