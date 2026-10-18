@use('App\Support\StockCheckTime')

<x-layouts.app :full-width="true">
    {{--
        The hero: the highlights from config/home.php, each over a photo under
        an ink scrim with a white card of its product. The tabs switch the
        highlight; nothing moves on its own. Below them, the recipe picks.
        On a phone the photo sits at the top and the text runs below it.
    --}}
    <section
        aria-labelledby="beranda-judul"
        class="relative overflow-hidden bg-ink text-white"
        x-data="{
            tab: 0,
            go(index) {
                const tabs = this.$root.querySelectorAll('[role=tab]');
                this.tab = (index + tabs.length) % tabs.length;
                this.$nextTick(() => tabs[this.tab].focus());
            },
        }"
    >
        @forelse ($highlights as $index => $highlight)
            <img
                src="{{ $highlight['photo'] }}"
                alt=""
                @if ($index > 0) hidden loading="lazy" @else fetchpriority="high" @endif
                x-bind:hidden="tab !== {{ $index }}"
                class="absolute inset-x-0 top-0 h-[26rem] w-full object-cover lg:inset-0 lg:h-full"
            >
        @empty
            <img src="{{ $fallbackPhoto }}" alt="" fetchpriority="high" class="absolute inset-x-0 top-0 h-[26rem] w-full object-cover lg:inset-0 lg:h-full">
        @endforelse
        <div aria-hidden="true" class="absolute inset-x-0 top-0 h-[26rem] bg-linear-to-b from-ink/10 via-ink/30 via-45% to-ink lg:inset-0 lg:h-full lg:bg-linear-to-r lg:from-ink/95 lg:via-ink/80 lg:via-50% lg:to-ink/25"></div>

        <div class="relative mx-auto flex w-full max-w-6xl flex-col px-4 pt-56 lg:pt-28">
            <h1 id="beranda-judul" class="sr-only">Hasil laut segar, dicek setiap pagi</h1>

            @forelse ($highlights as $index => $highlight)
                @php($product = $highlight['product'])
                @php($cheapest = $product->variants->first())
                <div
                    id="hero-sorotan-{{ $index }}"
                    @if (count($highlights) > 1) role="tabpanel" aria-labelledby="hero-tab-{{ $index }}" @endif
                    @if ($index > 0) hidden @endif
                    x-bind:hidden="tab !== {{ $index }}"
                    class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-end lg:gap-14"
                >
                    <div class="flex flex-col gap-4">
                        <p class="text-[2.375rem] leading-[1.04] font-medium tracking-[-0.035em] lg:text-[4rem] lg:leading-none">{{ $highlight['title'] }}</p>
                        <p class="max-w-[32em] text-base leading-relaxed text-white/85 lg:text-lg">{{ $highlight['text'] }}</p>
                        {{-- White on the photo: the one exception to the green primary button (docs/ui-guidelines.md). --}}
                        <a href="{{ route('products.show', $product->slug) }}" class="mt-1.5 flex h-13 items-center justify-center self-start rounded-full bg-white px-6 font-bold text-primary-800 hover:bg-surface focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white">{{ $highlight['button'] }}</a>
                    </div>

                    <a href="{{ route('products.show', $product->slug) }}" data-highlight-card class="grid grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-3.5 rounded-card bg-white p-3 text-ink focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white lg:grid-cols-[5.5rem_minmax(0,1fr)]">
                        @if ($product->images->first())
                            <img src="{{ $product->images->first()->thumbnailUrl() }}" alt="" loading="lazy" class="size-18 rounded-photo bg-subtle object-cover lg:size-22">
                        @else
                            <span class="size-18 rounded-photo bg-subtle lg:size-22" aria-hidden="true"></span>
                        @endif
                        <span class="flex min-w-0 flex-col gap-0.5">
                            @if ($product->last_checked_at)
                                <x-badge variant="ink" class="self-start">Dicek {{ StockCheckTime::format($product->last_checked_at) }}</x-badge>
                            @endif
                            <span class="mt-1 truncate font-semibold">{{ $product->name }}</span>
                            <span class="truncate text-xs text-muted">{{ $product->store->name }}, {{ $product->store->city }}</span>
                            @if ($cheapest)
                                <span class="text-sm"><span class="text-xs text-muted">Mulai</span> <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="text-base" /></span>
                            @endif
                        </span>
                    </a>
                </div>
            @empty
                <div class="flex flex-col gap-4">
                    <p class="text-[2.375rem] leading-[1.04] font-medium tracking-[-0.035em] lg:text-[4rem] lg:leading-none">Hasil laut segar, dicek setiap pagi</p>
                    <p class="max-w-[32em] text-base leading-relaxed text-white/85 lg:text-lg">Belanja langsung dari lapak penjual. Setiap produk menampilkan jam stoknya terakhir dicek.</p>
                    <a href="{{ route('catalog') }}" class="mt-1.5 flex h-13 items-center justify-center self-start rounded-full bg-white px-6 font-bold text-primary-800 hover:bg-surface focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white">Lihat katalog</a>
                </div>
            @endforelse

            @if (count($highlights) > 1)
                <div
                    role="tablist"
                    aria-label="Sorotan"
                    class="-mx-4 mt-8 flex snap-x scroll-px-4 gap-6 overflow-x-auto border-b border-white/20 px-4 lg:mx-0 lg:mt-12 lg:gap-9 lg:overflow-visible lg:px-0"
                    x-on:keydown.right.prevent="go(tab + 1)"
                    x-on:keydown.left.prevent="go(tab - 1)"
                >
                    @foreach ($highlights as $index => $highlight)
                        <button
                            type="button"
                            role="tab"
                            id="hero-tab-{{ $index }}"
                            aria-controls="hero-sorotan-{{ $index }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            tabindex="{{ $index === 0 ? '0' : '-1' }}"
                            x-bind:aria-selected="tab === {{ $index }}"
                            x-bind:tabindex="tab === {{ $index }} ? 0 : -1"
                            x-on:click="tab = {{ $index }}"
                            class="-mb-px shrink-0 snap-start border-b-3 border-transparent pt-2 pb-3 text-left text-sm text-white/70 aria-selected:border-white aria-selected:font-semibold aria-selected:text-white lg:text-base"
                        >{{ $highlight['product']->name }}</button>
                    @endforeach
                </div>
            @endif

            @if ($recipes !== [])
                <section aria-labelledby="resep-pilihan" class="mt-10 flex flex-col gap-4 pb-10 lg:mt-12 lg:pb-14">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="resep-pilihan" class="text-[1.375rem] leading-tight font-medium tracking-tight lg:text-[1.75rem]">Mau masak apa hari ini?</h2>
                        <a href="{{ route('recipes.index') }}" class="flex min-h-11 items-center text-sm font-semibold underline underline-offset-4 lg:text-base">Semua resep</a>
                    </div>
                    <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
                        @foreach ($recipes as $recipe)
                            <li>
                                <a href="{{ route('recipes.show', $recipe->slug) }}" class="group flex flex-col gap-2 focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-white">
                                    <img src="{{ asset($recipe->photo) }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-photo bg-white/10 object-cover">
                                    <span class="text-xs text-white/75">{{ $recipe->meta() }}</span>
                                    <span class="leading-tight font-semibold group-hover:underline lg:text-lg">{{ $recipe->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @else
                <div class="pb-12"></div>
            @endif
        </div>
    </section>

    @if ($products->isNotEmpty())
        <section aria-labelledby="baru-judul" x-data="{ kategori: '' }">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-9 pb-12 lg:pt-14 lg:pb-16">
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between lg:gap-x-8">
                    <div class="flex flex-col gap-1.5">
                        <h2 id="baru-judul" class="text-[1.625rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Baru dikonfirmasi</h2>
                        <p class="text-sm text-muted">Stok dicek penjual, diurutkan dari yang paling baru.</p>
                    </div>

                    @if ($chips->isNotEmpty())
                        <div role="group" aria-label="Kategori" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:justify-end lg:overflow-visible lg:px-0 lg:pb-0">
                            <button type="button" data-kategori="" aria-pressed="true" x-bind:aria-pressed="kategori === $el.dataset.kategori" x-on:click="kategori = $el.dataset.kategori" class="h-10 shrink-0 rounded-full bg-white px-3.5 text-sm font-semibold whitespace-nowrap text-ink aria-pressed:bg-ink aria-pressed:text-white lg:h-9">Semua</button>
                            @foreach ($chips as $category)
                                <button type="button" data-kategori="{{ $category->slug }}" aria-pressed="false" x-bind:aria-pressed="kategori === $el.dataset.kategori" x-on:click="kategori = $el.dataset.kategori" class="h-10 shrink-0 rounded-full bg-white px-3.5 text-sm font-semibold whitespace-nowrap text-ink aria-pressed:bg-ink aria-pressed:text-white lg:h-9">{{ $category->name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <ul class="grid grid-cols-2 gap-x-3 gap-y-5 lg:grid-cols-4 lg:gap-x-5 lg:gap-y-7">
                    @foreach ($products as $product)
                        <li data-kategori="{{ $rootSlugs[$product->id] ?? '' }}" x-show="kategori === '' || kategori === $el.dataset.kategori">
                            <x-product-card :product="$product" :label="$product->last_checked_at ? 'Dicek '.StockCheckTime::format($product->last_checked_at) : null" />
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('catalog') }}" class="flex min-h-11 items-center self-center font-semibold text-primary-700 underline underline-offset-5 lg:self-start">Lihat semua di katalog</a>
            </div>
        </section>
    @endif

    @if ($needs !== [])
        <section aria-labelledby="keperluan-judul">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 pb-12 lg:gap-6 lg:pb-16">
                <h2 id="keperluan-judul" class="text-[1.625rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Belanja untuk keperluan apa?</h2>
                <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
                    @foreach ($needs as $need)
                        <li>
                            <a href="{{ route('catalog', $need['query']) }}" class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-card bg-ink p-4 text-white focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:aspect-[4/3] lg:p-5">
                                <img src="{{ asset($need['photo']) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105 motion-reduce:transition-none">
                                <span aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-ink/90 via-ink/40 to-ink/0"></span>
                                <span class="relative text-lg leading-tight font-semibold lg:text-xl">{{ $need['title'] }}</span>
                                <span class="relative mt-1 text-sm leading-snug text-white/85">{{ $need['text'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($preOrderDays !== [])
        <section aria-labelledby="preorder-judul" class="bg-ink text-white" x-data="{ day: 0 }">
            <div class="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-10 lg:gap-6 lg:py-14">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-1.5">
                        <h2 id="preorder-judul" class="text-[1.625rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Pesan hari ini, tangkapan besok</h2>
                        <p class="max-w-[36em] text-sm text-white/80 lg:text-base">Penjual menyiapkan pesanan dari hasil tangkapan subuh. Jumlahnya per hari terbatas, jadi pesan lebih awal.</p>
                    </div>
                    <a href="{{ route('pre-orders') }}" class="flex min-h-11 items-center self-start font-semibold underline underline-offset-4 lg:self-auto">Semua pre order</a>
                </div>

                <div role="tablist" aria-label="Hari kirim" class="grid grid-cols-3 gap-2 lg:max-w-xl">
                    @foreach ($preOrderDays as $index => $day)
                        <button
                            type="button"
                            role="tab"
                            id="preorder-tab-{{ $index }}"
                            aria-controls="preorder-hari-{{ $index }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            x-bind:aria-selected="day === {{ $index }}"
                            x-on:click="day = {{ $index }}"
                            class="flex flex-col items-start rounded-option bg-white/10 px-3.5 py-2.5 text-left aria-selected:bg-white aria-selected:text-ink"
                        >
                            <span class="text-xs opacity-80">{{ $day['date']->locale('id')->translatedFormat('j M') }}</span>
                            <span class="font-semibold">{{ $day['label'] }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($preOrderDays as $index => $day)
                    <div
                        role="tabpanel"
                        id="preorder-hari-{{ $index }}"
                        aria-labelledby="preorder-tab-{{ $index }}"
                        @if ($index > 0) hidden @endif
                        x-bind:hidden="day !== {{ $index }}"
                    >
                        @if ($day['products'] === [])
                            <p class="rounded-card bg-white/10 p-5 text-white/85">Belum ada pre order yang bisa dikirim {{ mb_strtolower($day['label']) }}.</p>
                        @else
                            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:gap-4">
                                @foreach ($day['products'] as $item)
                                    @php($product = $item['product'])
                                    @php($cheapest = $product->variants->first())
                                    <li>
                                        <a href="{{ route('products.show', $product->slug) }}" class="grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-3.5 rounded-card bg-white p-2.5 text-ink focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white">
                                            @if ($product->images->first())
                                                <img src="{{ $product->images->first()->thumbnailUrl() }}" alt="" loading="lazy" class="size-22 rounded-photo bg-subtle object-cover">
                                            @else
                                                <x-empty-state compact title="Belum ada foto" class="size-22 rounded-photo" />
                                            @endif
                                            <span class="flex min-w-0 flex-col gap-0.5">
                                                <span class="truncate font-semibold">{{ $product->name }}</span>
                                                <span class="truncate text-xs text-muted">{{ $product->store->name }}</span>
                                                @if ($cheapest)
                                                    <span class="text-sm"><span class="text-xs text-muted">Mulai</span> <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="text-base" /></span>
                                                @endif
                                                @if ($product->daily_quota !== null && $cheapest)
                                                    <span class="text-xs font-semibold text-primary-800">Kuota {{ \App\Livewire\ProductPage::quantity((string) $product->daily_quota) }} {{ mb_strtolower($cheapest->unit->label()) }} per hari</span>
                                                @endif
                                                <span class="text-xs font-semibold text-accent-700">Pesan sebelum {{ $item['deadline'] }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($stores->isNotEmpty())
        <section aria-labelledby="lapak-judul">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 pt-10 pb-12 lg:pt-14 lg:pb-16">
                <h2 id="lapak-judul" class="text-[1.625rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Lapak terverifikasi</h2>

                <ul class="flex flex-col rounded-card bg-white px-4 lg:px-6">
                    @foreach ($stores as $store)
                        <li class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-b-0 lg:gap-5 lg:py-4">
                            @if ($store->logoUrl())
                                <img src="{{ $store->logoUrl() }}" alt="" loading="lazy" class="size-12 shrink-0 rounded-photo object-cover lg:size-14">
                            @else
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-photo bg-primary-50 text-xl font-semibold text-primary-800 lg:size-14" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                            @endif
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate font-semibold">{{ $store->name }}</span>
                                <span class="truncate text-sm text-muted">{{ $store->city }}, {{ $store->product_count }} produk</span>
                            </span>
                            <a href="{{ route('stores.show', $store->slug) }}" class="flex h-11 shrink-0 items-center rounded-full border-2 border-ink px-4 text-sm font-bold hover:bg-surface focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600" aria-label="Kunjungi lapak {{ $store->name }}">Kunjungi lapak</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section aria-labelledby="buka-lapak-judul" class="bg-primary-50">
        <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-10 lg:flex-row lg:items-center lg:justify-between lg:py-12">
            <div class="flex flex-col gap-1.5">
                <h2 id="buka-lapak-judul" class="text-[1.625rem] leading-tight font-medium tracking-tight text-primary-800 lg:text-[2.125rem]">Punya lapak hasil laut?</h2>
                <p class="max-w-[36em] text-primary-800">Jual ke pembeli di sekitar lapak Anda. Stok dicatat dari ponsel, pesanan masuk dengan alamat dan ongkos kirim yang jelas.</p>
            </div>
            <a href="{{ url('/seller') }}" class="flex h-13 shrink-0 items-center justify-center self-start rounded-full bg-primary-700 px-6 font-bold text-white hover:bg-primary-800 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-primary-600 lg:self-auto">Buka lapak</a>
        </div>
    </section>
</x-layouts.app>
