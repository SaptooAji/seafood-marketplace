<section aria-labelledby="galat-produk" class="flex flex-col gap-4">
    <div class="flex items-end justify-between gap-4">
        <h2 id="galat-produk" class="text-xl font-medium tracking-tight lg:text-2xl">Baru dikonfirmasi pagi ini</h2>
        <a href="{{ route('catalog') }}" class="flex min-h-11 items-center font-semibold text-primary-700 underline underline-offset-4"><span class="lg:hidden">Katalog</span><span class="hidden lg:inline">Lihat katalog</span></a>
    </div>

    <ul class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-5 lg:overflow-visible lg:px-0 lg:pb-0">
        @foreach ($products as $product)
            <li class="w-44 shrink-0 snap-start lg:w-auto">
                <x-product-card :product="$product" :label="'Dicek '.\App\Support\StockCheckTime::format($product->last_checked_at)" />
            </li>
        @endforeach
    </ul>
</section>
