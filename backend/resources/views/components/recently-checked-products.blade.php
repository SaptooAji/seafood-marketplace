{{--
    The card follows the 404 board: the photo (4:3 on the web, square on
    the phone), the store and when its stock was checked, the name, and the
    cheapest price per unit. Demo store products keep the "Toko contoh" label.
--}}
<section aria-labelledby="galat-produk" class="flex flex-col gap-3 lg:gap-4">
    <div class="flex items-center justify-between gap-3 lg:items-end lg:gap-4">
        <h2 id="galat-produk" class="text-xl font-medium tracking-tight lg:text-2xl">Baru dikonfirmasi pagi ini</h2>
        <a href="{{ route('catalog') }}" class="flex min-h-11 items-center text-sm font-semibold text-primary-700 underline underline-offset-4 lg:text-base"><span class="lg:hidden">Katalog</span><span class="hidden lg:inline">Lihat katalog</span></a>
    </div>

    <ul class="-mx-4 flex gap-3 overflow-x-auto px-4 [scrollbar-width:none] lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-5 lg:overflow-visible lg:px-0">
        @foreach ($products as $product)
            @php
                $image = $product->images->first();
                $cheapest = $product->variants->first();
            @endphp
            <li class="flex w-42 shrink-0 lg:w-auto">
                <a href="{{ route('products.show', $product->slug) }}" data-error-product class="group flex flex-1 flex-col gap-1.25 rounded-[1.25rem] bg-white p-2 pb-3 text-ink focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:gap-1.5 lg:rounded-card lg:p-2.5 lg:pb-3.5">
                    <div class="relative">
                        @if ($image)
                            <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full rounded-[0.875rem] bg-subtle object-cover lg:aspect-4/3 lg:rounded-photo">
                        @else
                            <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-[0.875rem] lg:aspect-4/3 lg:rounded-photo" />
                        @endif

                        @if ($product->store->is_demo)
                            <x-badge variant="demo" class="absolute right-2 bottom-2">Toko contoh</x-badge>
                        @endif
                    </div>

                    <span class="mt-0.5 truncate px-0.5 text-[0.6875rem] text-muted lg:mt-1 lg:px-1 lg:text-[0.8125rem]">{{ $product->store->name }}, dicek {{ \App\Support\StockCheckTime::format($product->last_checked_at) }}</span>
                    <span class="px-0.5 text-[0.9375rem] leading-tight font-semibold group-hover:text-primary-700 lg:px-1 lg:text-[1.0625rem]">{{ $product->name }}</span>
                    @if ($cheapest)
                        <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="mt-auto px-0.5 pt-0.5 text-[1.0625rem] lg:px-1 lg:pt-1 lg:text-[1.1875rem]" />
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</section>
