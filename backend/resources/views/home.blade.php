@use('App\Support\StockCheckTime')

<x-layouts.app :full-width="true">
    {{--
        The hero: a product photo as the background under an ink scrim, with
        white text on top. The tabs switch the photo and its caption. On a
        phone the photo sits at the top and the text runs below it.
    --}}
    <section
        aria-labelledby="beranda-judul"
        class="relative overflow-hidden bg-ink text-white lg:flex lg:min-h-[52rem] lg:flex-col lg:justify-end"
        x-data="{
            tab: 0,
            go(index) {
                const tabs = this.$root.querySelectorAll('[role=tab]');
                this.tab = (index + tabs.length) % tabs.length;
                this.$nextTick(() => tabs[this.tab].focus());
            },
        }"
    >
        @foreach ($heroProducts as $index => $product)
            <img
                src="{{ $product->images->first()->url() }}"
                alt="{{ $product->name }}"
                @if ($index > 0) hidden loading="lazy" @else fetchpriority="high" @endif
                x-bind:hidden="tab !== {{ $index }}"
                class="absolute inset-x-0 top-0 h-[32.5rem] w-full object-cover lg:inset-0 lg:h-full"
            >
        @endforeach
        @if ($heroProducts->isNotEmpty())
            <div aria-hidden="true" class="absolute inset-x-0 top-0 h-[32.5rem] bg-linear-to-b from-ink/5 via-ink/20 via-40% to-ink lg:inset-0 lg:h-full lg:bg-linear-to-t lg:from-ink/95 lg:via-ink/85 lg:via-45% lg:to-ink/5"></div>
        @endif

        <div class="relative mx-auto flex w-full max-w-6xl flex-col px-4 pt-4 lg:pt-8">
            @foreach ($heroProducts as $index => $product)
                @php($cheapest = $product->variants->first())
                <p
                    id="hero-keterangan-{{ $index }}"
                    @if ($index > 0) hidden @endif
                    x-bind:hidden="tab !== {{ $index }}"
                    class="order-1 max-w-md self-start rounded-option bg-ink px-4 py-3 text-sm leading-relaxed lg:self-end lg:text-base"
                >
                    <strong class="font-semibold">{{ $product->name }}</strong> dari {{ $product->store->name }}@if ($product->last_checked_at), dicek {{ StockCheckTime::format($product->last_checked_at) }}@endif
                    <br>
                    @if ($cheapest)
                        <span class="tabular-nums">Rp{{ number_format($cheapest->price, 0, ',', '.') }} per {{ $cheapest->unit->label() }}</span>,
                    @endif
                    <a href="{{ route('products.show', $product->slug) }}" class="font-semibold underline underline-offset-4">lihat produk</a>
                </p>
            @endforeach

            @if ($heroProducts->count() > 1)
                <div
                    role="tablist"
                    aria-label="Produk yang baru dicek"
                    class="order-2 -mx-4 mt-48 flex snap-x scroll-px-4 gap-6 overflow-x-auto border-b border-white/20 px-4 lg:order-5 lg:mx-0 lg:mt-0 lg:gap-9 lg:overflow-visible lg:border-t lg:border-b-0 lg:px-0"
                    x-on:keydown.right.prevent="go(tab + 1)"
                    x-on:keydown.left.prevent="go(tab - 1)"
                >
                    @foreach ($heroProducts as $index => $product)
                        <button
                            type="button"
                            role="tab"
                            id="hero-tab-{{ $index }}"
                            aria-controls="hero-keterangan-{{ $index }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            tabindex="{{ $index === 0 ? '0' : '-1' }}"
                            x-bind:aria-selected="tab === {{ $index }}"
                            x-bind:tabindex="tab === {{ $index }} ? 0 : -1"
                            x-on:click="tab = {{ $index }}"
                            class="group -mb-px flex shrink-0 snap-start items-center gap-3 border-b-3 border-transparent pt-3 pb-3.5 text-left text-white/75 aria-selected:border-white aria-selected:text-white lg:-mt-px lg:mb-0 lg:border-t-3 lg:border-b-0 lg:pt-4 lg:pb-6"
                        >
                            <img src="{{ $product->images->first()->thumbnailUrl() }}" alt="" loading="lazy" class="size-10 rounded-full object-cover opacity-70 group-aria-selected:opacity-100 lg:size-11">
                            <span class="flex flex-col">
                                @if ($product->last_checked_at)
                                    <span class="text-xs tabular-nums">{{ StockCheckTime::format($product->last_checked_at) }}</span>
                                @endif
                                <span class="max-w-40 truncate text-base group-aria-selected:font-semibold lg:text-lg">{{ $product->name }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div @class([
                'order-3 flex flex-col gap-4 pt-7 lg:order-2 lg:max-w-2xl lg:pt-0',
                'mt-48' => $heroProducts->count() === 1,
                'lg:mt-64' => $heroProducts->isNotEmpty(),
                'lg:mt-24' => $heroProducts->isEmpty(),
            ])>
                <h1 id="beranda-judul" class="text-[2.5rem] leading-[1.04] font-medium tracking-[-0.035em] lg:text-[4.25rem] lg:leading-none">Hasil laut segar, dicek setiap pagi</h1>
                <p class="max-w-[32em] text-base leading-relaxed text-white/85 lg:text-lg">Belanja langsung dari lapak penjual. Setiap produk menampilkan jam stoknya terakhir dicek.</p>
                <div class="mt-1.5 flex flex-col gap-x-7 gap-y-1 sm:flex-row sm:items-center">
                    {{-- White on the photo: the one exception to the green primary button (docs/ui-guidelines.md). --}}
                    <a href="{{ route('catalog') }}#toko-terdekat" class="flex h-13 items-center justify-center rounded-full bg-white px-6 font-bold text-primary-800 hover:bg-surface focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white">Cari lapak terdekat</a>
                    <a href="{{ route('catalog') }}" class="flex min-h-11 items-center self-center font-semibold underline underline-offset-5 sm:self-auto">Lihat katalog</a>
                </div>
            </div>

            @if ($freshCount > 0 || $activeStoreCount > 0 || $conditionCounts !== [])
                <dl class="order-4 mt-6 mb-9 grid grid-cols-2 gap-x-5 gap-y-6 lg:order-3 lg:mt-11 lg:mb-11 lg:grid-cols-3 lg:gap-x-10">
                    @if ($freshCount > 0)
                        <div class="flex flex-col gap-1 border-t-2 border-white/70 pt-3">
                            <dt class="text-sm text-white/80">Stok dicek dalam {{ config()->integer('catalog.stale_stock_hours') }} jam</dt>
                            <dd class="text-[1.75rem] leading-tight font-medium tracking-tight lg:text-[2.375rem]">{{ $freshCount }} produk</dd>
                        </div>
                    @endif
                    @if ($activeStoreCount > 0)
                        <div class="flex flex-col gap-1 border-t-2 border-white/70 pt-3">
                            <dt class="text-sm text-white/80">Lapak aktif</dt>
                            <dd class="text-[1.75rem] leading-tight font-medium tracking-tight lg:text-[2.375rem]">{{ $activeStoreCount }} lapak</dd>
                        </div>
                    @endif
                    @if ($conditionCounts !== [])
                        <div class="col-span-2 flex flex-col gap-1 border-t-2 border-white/70 pt-3 lg:col-span-1">
                            <dt class="text-sm text-white/80">Belanja menurut kondisi</dt>
                            <dd class="flex flex-wrap gap-x-5">
                                @foreach ($conditionCounts as $value => $item)
                                    <a href="{{ route('catalog', ['kondisi' => $value]) }}" class="flex min-h-11 items-baseline gap-1.5 pt-1.5 text-[1.375rem] font-medium underline-offset-5 hover:underline lg:text-2xl">{{ $item['condition']->label() }} <span class="text-sm text-white/75">{{ $item['count'] }}</span></a>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            @else
                <div class="order-4 pb-12"></div>
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

    @if ($stores->isNotEmpty())
        <section aria-labelledby="lapak-judul" class="border-t border-line">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 pt-9 pb-14 lg:pt-12 lg:pb-16">
                <h2 id="lapak-judul" class="text-[1.625rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Lapak terverifikasi</h2>

                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:gap-4">
                    @foreach ($stores as $store)
                        <li>
                            <a href="{{ route('stores.show', $store->slug) }}" class="flex items-center gap-3.5 rounded-option border border-line bg-white p-3.5 hover:border-primary-600 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                <span class="flex size-14 shrink-0 items-center justify-center rounded-photo bg-primary-50 text-xl font-semibold text-primary-800" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold">{{ $store->name }}</span>
                                    <span class="block truncate text-sm text-muted">{{ $store->city }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.app>
