<x-layouts.app>
    <section>
        <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-1 text-slate-600">Hasil laut segar langsung dari penjual.</p>
    </section>

    <section class="mt-8" aria-labelledby="latest-products">
        <div class="flex items-baseline justify-between">
            <h2 id="latest-products" class="text-lg font-semibold">Produk terbaru</h2>
            <a href="{{ url('/produk') }}" class="text-sm text-teal-700 hover:underline">Lihat semua</a>
        </div>

        @if ($products->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Belum ada produk.</p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($stores->isNotEmpty())
        <section class="mt-8" aria-labelledby="verified-stores">
            <h2 id="verified-stores" class="text-lg font-semibold">Toko terverifikasi</h2>

            <ul class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($stores as $store)
                    <li>
                        <a href="{{ url('/toko/'.$store->slug) }}" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 hover:border-teal-600">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-50 font-semibold text-teal-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ $store->name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $store->city }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
