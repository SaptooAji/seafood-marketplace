<x-layouts.app
    :title="$store->name.', '.$store->city"
    :description="$store->metaDescription()"
    :image="$store->logoUrl()"
>
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into < and >, so seller text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    <section class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-start">
        @if ($store->logoUrl())
            <img src="{{ $store->logoUrl() }}" alt="Logo {{ $store->name }}" class="size-20 shrink-0 rounded-full object-cover">
        @else
            <span class="flex size-20 shrink-0 items-center justify-center rounded-full bg-teal-50 text-2xl font-semibold text-teal-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
        @endif

        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold">{{ $store->name }}</h1>
            <p class="text-sm text-slate-600">{{ $store->city }}</p>

            @if ($store->description)
                <p class="mt-3 whitespace-pre-line text-slate-700">{{ $store->description }}</p>
            @endif

            <p class="mt-3 text-sm text-slate-600">{{ $store->address }}</p>

            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <a href="{{ $store->whatsappUrl('Halo '.$store->name.', saya ingin bertanya tentang produk Anda.') }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700">WhatsApp</a>
                <a href="tel:{{ $store->internationalPhone() }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Telepon</a>
                <a href="{{ $store->googleMapsUrl() }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Lihat di Google Maps</a>
            </div>
        </div>
    </section>

    <section class="mt-8" aria-labelledby="store-products">
        <h2 id="store-products" class="text-lg font-semibold">Produk</h2>

        @if ($products->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Toko ini belum punya produk yang dijual.</p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $products->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
