<x-layouts.app
    :title="$store->name.', '.$store->city"
    :description="$store->metaDescription()"
    :image="$store->logoUrl()"
>
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into < and >, so seller text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    <div class="flex flex-col gap-8 lg:gap-10">
        <section aria-labelledby="nama-toko" class="flex flex-col gap-5 rounded-feature bg-white p-5 sm:flex-row sm:items-start sm:gap-6 lg:p-7">
            @if ($store->logoUrl())
                <img src="{{ $store->logoUrl() }}" alt="Logo {{ $store->name }}" class="size-20 shrink-0 rounded-card object-cover lg:size-24">
            @else
                <span class="flex size-20 shrink-0 items-center justify-center rounded-card bg-primary-50 text-3xl font-extrabold text-primary-700 lg:size-24" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
            @endif

            <div class="flex min-w-0 flex-1 flex-col gap-3">
                <div>
                    <h1 id="nama-toko" class="text-[1.75rem] leading-tight font-extrabold tracking-tight lg:text-[2.125rem]">{{ $store->name }}</h1>
                    <p class="mt-1 text-muted">{{ $store->city }}</p>
                </div>

                @if ($store->description)
                    <p class="max-w-prose leading-relaxed whitespace-pre-line">{{ $store->description }}</p>
                @endif

                <p class="text-sm text-muted">{{ $store->address }}</p>

                <div class="mt-1 flex flex-wrap gap-2">
                    <x-button :href="$store->whatsappUrl('Halo '.$store->name.', saya ingin bertanya tentang produk Anda.')" target="_blank" rel="noopener noreferrer">WhatsApp</x-button>
                    <x-button variant="secondary" :href="'tel:'.$store->internationalPhone()">Telepon</x-button>
                    <x-button variant="secondary" :href="$store->googleMapsUrl()" target="_blank" rel="noopener noreferrer">Lihat di Google Maps</x-button>
                </div>
            </div>
        </section>

        <section aria-labelledby="produk-toko" class="flex flex-col gap-4">
            <div class="flex items-baseline justify-between gap-4">
                <h2 id="produk-toko" class="text-[1.375rem] font-extrabold tracking-tight lg:text-[1.75rem]">Produk</h2>
                @if ($products->total() > 0)
                    <p class="text-sm font-bold">{{ $products->total() }} produk</p>
                @endif
            </div>

            @if ($products->isEmpty())
                <x-empty-state
                    title="Toko ini belum punya produk yang dijual"
                    text="Lihat produk dari toko lain di katalog."
                    action="Lihat katalog"
                    :href="route('catalog')"
                />
            @else
                <ul class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4 lg:gap-x-5 lg:gap-y-7">
                    @foreach ($products as $product)
                        <li>
                            <x-product-card :product="$product" />
                        </li>
                    @endforeach
                </ul>

                {{ $products->links('pagination') }}
            @endif
        </section>
    </div>
</x-layouts.app>
