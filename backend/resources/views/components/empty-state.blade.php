@props(['title', 'text' => null, 'action' => null, 'href' => null, 'compact' => false])

{{--
    What to show when there is nothing to list, with an optional way forward.
    Compact is the quiet version that fills a photo frame ("Belum ada foto").
--}}
@if ($compact)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center bg-subtle p-3 text-center text-xs font-medium text-muted']) }}>
        <p>{{ $title }}</p>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 rounded-card bg-white px-6 py-10 text-center']) }}>
        <p class="text-lg font-extrabold tracking-tight text-ink">{{ $title }}</p>
        @if (filled($text))
            <p class="max-w-sm text-sm text-muted">{{ $text }}</p>
        @endif
        @if (filled($action) && filled($href))
            <x-button :href="$href" variant="secondary" class="mt-2">{{ $action }}</x-button>
        @endif
        {{ $slot }}
    </div>
@endif
