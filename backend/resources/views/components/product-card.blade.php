@props(['product', 'distance' => null])

@php
    $image = $product->images->first();
    $cheapest = $product->variants->first();
@endphp

<a href="{{ url('/produk/'.$product->slug) }}" class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white hover:border-teal-600">
    @if ($image)
        <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full object-cover">
    @else
        <div class="flex aspect-square w-full items-center justify-center bg-slate-100 text-xs text-slate-400">Belum ada foto</div>
    @endif

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h3 class="line-clamp-2 text-sm font-medium group-hover:text-teal-700">{{ $product->name }}</h3>

        @if ($cheapest)
            <p class="text-sm">
                <span class="text-xs text-slate-500">Mulai</span>
                <span class="font-semibold">Rp{{ number_format($cheapest->price, 0, ',', '.') }}</span>
                <span class="text-xs text-slate-500">/ {{ $cheapest->unit->label() }}</span>
            </p>
        @endif

        <p class="mt-auto truncate text-xs text-slate-500">
            {{ $product->store->name }} &middot; {{ $product->store->city }}
            @if ($distance !== null)
                &middot; {{ number_format((float) $distance, 1, ',', '.') }} km
            @endif
        </p>
    </div>
</a>
