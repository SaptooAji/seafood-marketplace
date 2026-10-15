@props(['product', 'distance' => null, 'label' => null])

{{--
    A product in a list: the photo with an optional label over it (when the
    stock was checked), the name, the store, and the starting price.
--}}
@php
    $image = $product->images->first();
    $cheapest = $product->variants->first();
@endphp

<a href="{{ route('products.show', $product->slug) }}" {{ $attributes->merge(['class' => 'group flex flex-col gap-1.5 rounded-card focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600']) }}>
    <div class="relative">
        @if ($image)
            <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full rounded-card bg-subtle object-cover">
        @else
            <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-card" />
        @endif

        @if (filled($label))
            <x-badge variant="ink" class="absolute top-2 left-2">{{ $label }}</x-badge>
        @endif
    </div>

    <h3 class="mt-1 line-clamp-2 text-base font-semibold leading-snug group-hover:text-primary-700">{{ $product->name }}</h3>

    <p class="truncate text-xs text-muted">
        {{ $product->store->name }}, {{ $product->store->city }}@if ($distance !== null), {{ number_format((float) $distance, 1, ',', '.') }} km @endif
    </p>

    @if ($cheapest)
        <p class="mt-auto text-sm">
            <span class="text-xs text-muted">Mulai</span>
            <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="text-lg" />
        </p>
    @endif
</a>
