@props(['product', 'distance' => null])

@php
    $image = $product->images->first();
    $cheapest = $product->variants->first();
@endphp

<a href="{{ route('products.show', $product->slug) }}" class="group flex flex-col gap-2 rounded-card bg-white p-2.5 pb-3.5 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
    @if ($image)
        <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full rounded-photo object-cover">
    @else
        <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-photo" />
    @endif

    <h3 class="line-clamp-2 px-1 text-base font-semibold leading-snug group-hover:text-primary-700">{{ $product->name }}</h3>

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
