@props(['product', 'distance' => null, 'label' => null, 'tone' => 'white'])

{{--
    A product in a list, on a white card: the photo with an optional label
    over it (when the stock was checked), the name, the store, and the
    starting price. `tone="surface"` gives a sand card for white bands.
    Products of a demo store carry the "Toko contoh" label.
--}}
@php
    $image = $product->images->first();
    $cheapest = $product->variants->first();
    $tone = in_array($tone, ['white', 'surface'], true) ? $tone : 'white';
@endphp

<a href="{{ route('products.show', $product->slug) }}" data-tone="{{ $tone }}" {{ $attributes->merge(['class' => 'group flex h-full flex-col gap-1.5 rounded-card p-2.5 pb-3.5 focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 '.($tone === 'surface' ? 'bg-surface' : 'bg-white')]) }}>
    <div class="relative">
        @if ($image)
            <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full rounded-photo bg-subtle object-cover">
        @else
            <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-photo" />
        @endif

        @if (filled($label))
            <x-badge variant="ink" class="absolute top-2 left-2">{{ $label }}</x-badge>
        @endif

        @if ($product->store->is_demo)
            <x-badge variant="demo" class="absolute right-2 bottom-2">Toko contoh</x-badge>
        @endif
    </div>

    <h3 class="mt-1 line-clamp-2 px-1 text-base font-semibold leading-snug group-hover:text-primary-700">{{ $product->name }}</h3>

    <p class="truncate px-1 text-xs text-muted">
        {{ $product->store->name }}, {{ $product->store->city }}@if ($distance !== null), {{ number_format((float) $distance, 1, ',', '.') }} km @endif
    </p>

    @if ($cheapest)
        <p class="mt-auto px-1 text-sm">
            <span class="text-xs text-muted">Mulai</span>
            <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="text-lg" />
        </p>
    @endif
</a>
