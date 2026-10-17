<x-layouts.app
    :title="$store->name.', '.$store->city"
    :description="$store->metaDescription()"
    :image="$store->logoUrl()"
    :noindex="$store->is_demo"
>
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into < and >, so seller text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    @php($initial = mb_strtoupper(mb_substr($store->name, 0, 1)))

    <div class="flex flex-col gap-10 lg:gap-12">
        {{-- Board "Toko R1": a framed cover with the identity card over its lower edge. --}}
        <section aria-labelledby="nama-toko">
            @if ($cover)
                <img data-store-cover src="{{ $cover->url() }}" alt="" class="h-48 w-full rounded-feature bg-subtle object-cover sm:h-64 lg:h-[360px]">
            @else
                <div data-store-cover="polos" aria-hidden="true" class="h-32 w-full rounded-feature bg-subtle sm:h-48 lg:h-[280px]"></div>
            @endif

            <div class="relative mx-2 -mt-16 flex flex-col gap-5 rounded-feature bg-white p-5 sm:mx-6 sm:-mt-20 lg:mx-10 lg:-mt-28 lg:gap-6 lg:px-8 lg:py-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-start gap-4 lg:items-center lg:gap-5">
                        @if ($store->logoUrl())
                            <img src="{{ $store->logoUrl() }}" alt="Logo {{ $store->name }}" class="-mt-12 size-20 shrink-0 rounded-card border-6 border-white bg-white object-cover lg:-mt-16 lg:size-24">
                        @else
                            <span aria-hidden="true" class="-mt-12 flex size-20 shrink-0 items-center justify-center rounded-card border-6 border-white bg-primary-700 text-3xl font-extrabold text-white lg:-mt-16 lg:size-24 lg:text-4xl">{{ $initial }}</span>
                        @endif
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <div>
                                @if ($store->is_demo)
                                    <x-badge variant="demo">Toko contoh</x-badge>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-0.5 text-[0.8125rem] font-semibold text-primary-800">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                                        Lapak terverifikasi
                                    </span>
                                @endif
                            </div>
                            <h1 id="nama-toko" class="text-[1.875rem] leading-none font-medium tracking-tight lg:text-[2.75rem]">{{ $store->name }}</h1>
                            <p class="text-muted">{{ $store->city }}</p>
                        </div>
                    </div>

                    @if ($store->is_demo)
                        {{-- A demo store is an example: no phone, chat, or directions. --}}
                        <p role="note" class="max-w-[34em] rounded-option border-[1.5px] border-dashed border-ink px-4 py-3 text-sm leading-relaxed">Ini toko contoh untuk memperlihatkan tampilan marketplace. Nama, harga, dan stoknya bukan sungguhan, jadi produknya tidak bisa dipesan.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            <x-button :href="$store->whatsappUrl('Halo '.$store->name.', saya ingin bertanya tentang produk Anda.')" target="_blank" rel="noopener noreferrer">Chat WhatsApp</x-button>
                            <x-button variant="secondary" :href="'tel:'.$store->internationalPhone()">Telepon</x-button>
                            <x-button variant="secondary" :href="$directionsUrl" target="_blank" rel="noopener noreferrer">Petunjuk arah</x-button>
                        </div>
                    @endif
                </div>

                <dl class="grid grid-cols-3 gap-4 border-t border-line pt-5 lg:gap-6">
                    <div class="flex flex-col gap-1">
                        <dt class="text-[0.8125rem] text-muted">Stok dicek</dt>
                        <dd class="font-semibold lg:text-lg">{{ $checkedAt ?? 'Belum dicek' }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-[0.8125rem] text-muted">Produk</dt>
                        <dd class="font-semibold lg:text-lg">{{ $productCount }} dijual</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-[0.8125rem] text-muted">Terima pesanan</dt>
                        <dd class="font-semibold lg:text-lg">{{ $receives }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- From lg the about card sits on the left and follows the scroll; on a phone it comes after the products. --}}
        <div class="grid gap-8 lg:grid-cols-[300px_minmax(0,1fr)] lg:items-start lg:gap-10">
            <section aria-labelledby="produk-toko" class="flex min-w-0 flex-col gap-5 lg:col-start-2 lg:row-start-1">
                <h2 id="produk-toko" class="text-[1.5rem] font-medium tracking-tight lg:text-[1.875rem]">Produk di lapak ini</h2>

                @if ($tabs !== [])
                    <nav aria-label="Kategori di lapak ini" class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
                        <ul class="flex min-w-max gap-6 border-b border-line">
                            <li>
                                <a href="{{ route('stores.show', $store->slug) }}" @if ($chosenTab === null) aria-current="page" @endif class="-mb-px flex h-12 items-center gap-1.5 border-b-3 border-transparent px-0.5 font-medium aria-[current=page]:border-ink aria-[current=page]:font-bold">Semua <span class="font-medium text-muted">{{ $productCount }}</span></a>
                            </li>
                            @foreach ($tabs as $tab)
                                <li>
                                    <a href="{{ route('stores.show', ['slug' => $store->slug, 'kategori' => $tab['slug']]) }}" @if ($chosenTab === $tab['slug']) aria-current="page" @endif class="-mb-px flex h-12 items-center gap-1.5 border-b-3 border-transparent px-0.5 font-medium aria-[current=page]:border-ink aria-[current=page]:font-bold">{{ $tab['name'] }} <span class="font-medium text-muted">{{ $tab['count'] }}</span></a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                @if ($products->isEmpty())
                    <x-empty-state
                        title="Toko ini belum punya produk yang dijual"
                        text="Lihat produk dari toko lain di katalog."
                        action="Lihat katalog"
                        :href="route('catalog')"
                    />
                @else
                    <ul class="grid grid-cols-2 gap-x-3 gap-y-5 lg:grid-cols-3 lg:gap-x-5 lg:gap-y-7">
                        @foreach ($products as $product)
                            <li>
                                <x-product-card :product="$product" />
                            </li>
                        @endforeach
                    </ul>

                    {{ $products->links('pagination') }}
                @endif
            </section>

            <aside aria-label="Tentang lapak" class="flex flex-col overflow-hidden rounded-feature bg-white lg:sticky lg:top-6 lg:col-start-1 lg:row-start-1">
                @if ($store->description)
                    <section class="flex flex-col gap-2.5 px-6 pt-6 pb-5">
                        <h2 class="text-lg font-semibold">Tentang lapak</h2>
                        <p class="leading-relaxed whitespace-pre-line">{{ $store->description }}</p>
                    </section>
                @endif
                {{-- A drawn map, not map tiles: no third-party script or request. --}}
                <svg aria-hidden="true" viewBox="0 0 300 180" preserveAspectRatio="xMidYMid slice" class="h-44 w-full bg-primary-50">
                    <path d="M0 130 C 70 115, 120 150, 180 138 S 260 112, 300 124 L 300 180 L 0 180 Z" fill="#CFE3D7"/>
                    <path d="M30 0 L 70 180 M 0 60 L 300 82 M 200 0 L 178 180" stroke="#FFFFFF" stroke-width="7" fill="none" opacity="0.85"/>
                    <g transform="translate(128 52) scale(1.8)">
                        <path d="M12 22s-7-6.2-7-11.5A7 7 0 0 1 19 10.5C19 15.8 12 22 12 22z" fill="#2F5D46" stroke="#FFFFFF" stroke-width="1.5"/>
                        <circle cx="12" cy="10.5" r="2.5" fill="#FFFFFF"/>
                    </g>
                </svg>
                <div class="flex flex-col gap-2.5 px-6 pt-4 pb-5">
                    <p class="leading-normal">{{ $store->address }}</p>
                    @unless ($store->is_demo)
                        <a href="{{ $store->googleMapsUrl() }}" target="_blank" rel="noopener noreferrer" class="flex min-h-11 items-center self-start font-semibold text-primary-700 underline underline-offset-4">Buka di Google Maps</a>
                    @endunless
                </div>
            </aside>
        </div>

        @if ($nearby->isNotEmpty())
            <section aria-labelledby="lapak-sekitar" class="flex flex-col gap-6 bg-white py-10 [box-shadow:0_0_0_100vmax_#fff] [clip-path:inset(0_-100vmax)] lg:py-14">
                <div class="flex flex-col gap-1">
                    <h2 id="lapak-sekitar" class="text-[1.5rem] font-medium tracking-tight lg:text-[1.875rem]">Lapak lain di sekitarnya</h2>
                    <p class="text-muted">Jarak dihitung dari {{ $store->name }}.</p>
                </div>
                <ul class="flex flex-col">
                    @foreach ($nearby as $other)
                        <li class="grid gap-4 border-t border-line py-5 lg:grid-cols-[17.5rem_minmax(0,1fr)_auto] lg:items-center lg:gap-7">
                            <div class="flex items-center gap-3.5">
                                @if ($other->logoUrl())
                                    <img src="{{ $other->logoUrl() }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-card object-cover">
                                @else
                                    <span aria-hidden="true" class="flex size-14 shrink-0 items-center justify-center rounded-card bg-primary-50 text-[1.375rem] font-extrabold text-primary-700">{{ mb_strtoupper(mb_substr($other->name, 0, 1)) }}</span>
                                @endif
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="truncate text-lg font-semibold">{{ $other->name }}</span>
                                    <span class="flex flex-wrap items-center gap-2 text-sm text-muted">
                                        {{ number_format((float) $other->distance_km, 1, ',', '.') }} km, {{ $other->product_count }} produk
                                        @if ($other->is_demo)
                                            <x-badge variant="demo">Toko contoh</x-badge>
                                        @endif
                                    </span>
                                </span>
                            </div>
                            <ul aria-label="Produk dari {{ $other->name }}" class="grid gap-3 sm:grid-cols-3">
                                @foreach ($nearbyProducts[$other->id] ?? [] as $item)
                                    @php($itemPrice = $item->variants->first())
                                    <li>
                                        <a href="{{ route('products.show', $item->slug) }}" class="grid grid-cols-[4rem_minmax(0,1fr)] items-center gap-3 rounded-card bg-surface p-2 hover:bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                            @if ($item->images->first())
                                                <img src="{{ $item->images->first()->thumbnailUrl() }}" alt="" loading="lazy" class="size-16 rounded-photo object-cover">
                                            @else
                                                <x-empty-state compact title="Belum ada foto" class="size-16 rounded-photo p-1 text-[0.625rem]" />
                                            @endif
                                            <span class="flex min-w-0 flex-col gap-0.5">
                                                <span class="truncate font-semibold">{{ $item->name }}</span>
                                                @if ($itemPrice)
                                                    <span class="text-sm"><span class="text-xs text-muted">Mulai</span> <x-price :amount="$itemPrice->price" :unit="$itemPrice->unit->label()" /></span>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('stores.show', $other->slug) }}" class="flex h-11 items-center justify-center self-start rounded-full border-2 border-ink px-4.5 text-sm font-bold whitespace-nowrap hover:bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 lg:self-auto">
                                Kunjungi lapak
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
