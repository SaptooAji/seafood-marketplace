{{--
    On a phone the filters open as a sheet from the bottom, from the sticky
    "Saring" bar; Escape or the close button shuts it and focus goes back to
    "Saring". From lg they are a sidebar.
--}}
<div
    x-data="{ open: false, close() { this.open = false; this.$nextTick(() => this.$refs.saring.focus()) } }"
    x-on:keydown.escape.window="if (open) close()"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    class="flex flex-col gap-5"
>
    <h1 class="text-[1.75rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Katalog</h1>

    @if ($categoryCards !== [])
        {{-- Scrolls sideways on a phone; from lg six cards fill the row. --}}
        <ul aria-label="Kategori" class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 lg:mx-0 lg:scroll-px-0 lg:px-0">
            @foreach ($categoryCards as $card)
                <li wire:key="category-card-{{ $card['category']->id }}" class="w-36 shrink-0 snap-start lg:w-[calc((100%-3.75rem)/6)]">
                    <button
                        type="button"
                        data-category-card="{{ $card['category']->slug }}"
                        wire:click="toggleCategory({{ \Illuminate\Support\Js::from($card['category']->slug) }})"
                        aria-pressed="{{ $category === $card['category']->slug ? 'true' : 'false' }}"
                        class="group flex w-full flex-col gap-2 rounded-card bg-white p-2 text-left hover:bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 aria-pressed:bg-ink aria-pressed:text-white"
                    >
                        @if ($card['photo'])
                            <img src="{{ $card['photo']->thumbnailUrl() }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-photo bg-subtle object-cover">
                        @else
                            <x-empty-state compact title="Belum ada foto" class="aspect-[4/3] w-full rounded-photo" />
                        @endif
                        <span class="flex flex-col px-1 pb-1">
                            <span class="truncate font-semibold">{{ $card['category']->name }}</span>
                            <span class="text-xs text-muted group-aria-pressed:text-white/80">{{ $card['count'] }} produk</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8">
        <div x-show="open" x-cloak x-on:click="close()" class="fixed inset-0 z-40 bg-ink/50 lg:hidden" aria-hidden="true"></div>

        <aside
            id="panel-filter"
            aria-labelledby="panel-filter-judul"
            data-open="false"
            x-bind:data-open="open"
            x-bind:role="open ? 'dialog' : null"
            x-bind:aria-modal="open ? 'true' : null"
            class="fixed inset-x-0 bottom-0 z-50 hidden max-h-[85dvh] flex-col rounded-t-card bg-white data-[open=true]:flex lg:static lg:z-auto lg:flex lg:max-h-none lg:w-72 lg:shrink-0 lg:rounded-card"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line py-2 pr-2 pl-4 lg:px-5 lg:pt-5 lg:pb-0 lg:border-b-0">
                <h2 id="panel-filter-judul" class="text-lg font-semibold">Saring</h2>
                <button type="button" x-ref="tutup" x-on:click="close()" aria-label="Tutup saring" class="flex size-11 items-center justify-center rounded-full hover:bg-subtle focus-visible:outline-3 focus-visible:outline-primary-600 lg:hidden">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="flex flex-col gap-6 overflow-y-auto p-4 lg:p-5">
                <x-input name="q" label="Cari" type="search" wire:model.live.debounce.400ms="q" maxlength="{{ \App\Livewire\Catalog::MAX_QUERY_LENGTH }}" placeholder="Nama produk" />

                <fieldset class="flex flex-col gap-1">
                    <legend class="mb-1.5 text-sm font-bold">Kondisi</legend>
                    @foreach ($conditionOptions as $option)
                        <label wire:key="condition-{{ $option['value'] }}" data-condition-option="{{ $option['value'] }}" data-count="{{ $option['count'] }}" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-option px-3 hover:bg-subtle has-checked:bg-primary-50 has-checked:font-semibold has-focus-visible:outline-3 has-focus-visible:outline-primary-600">
                            <input type="radio" name="condition" value="{{ $option['value'] }}" wire:model.live="condition" class="size-4.5 accent-primary-700 focus:outline-none">
                            <span class="flex-1">{{ $option['label'] }}</span>
                            <span class="text-sm text-muted tabular-nums">{{ $option['count'] }}</span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="flex items-center justify-between gap-3">
                    <span class="flex flex-col">
                        <span id="label-pre-order" class="text-sm font-bold">Hanya pre order</span>
                        <span class="text-xs text-muted">Dipesan dulu, dikirim sesuai jadwal penjual</span>
                    </span>
                    <button
                        type="button"
                        role="switch"
                        aria-checked="{{ $preOrder === '1' ? 'true' : 'false' }}"
                        aria-labelledby="label-pre-order"
                        wire:click="togglePreOrder"
                        class="group relative h-7 w-12 shrink-0 rounded-full bg-line-strong focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 aria-checked:bg-primary-700"
                    >
                        <span class="absolute top-1 left-1 size-5 rounded-full bg-white transition-transform group-aria-checked:translate-x-5 motion-reduce:transition-none"></span>
                    </button>
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1.5 text-sm font-bold">Harga (Rp)</legend>
                    @if ($priceBars !== [])
                        @php
                            $tallest = max(1, ...array_column($priceBars, 'count'));
                            // Literal classes so Tailwind finds them.
                            $heights = ['h-1/10', 'h-2/10', 'h-3/10', 'h-4/10', 'h-5/10', 'h-6/10', 'h-7/10', 'h-8/10', 'h-9/10', 'h-full'];
                            $rupiah = fn (int $amount) => 'Rp'.number_format($amount, 0, ',', '.');
                        @endphp
                        <ul aria-label="Sebaran harga produk" class="flex h-14 items-end gap-1">
                            @foreach ($priceBars as $index => $bar)
                                <li wire:key="price-bar-{{ $index }}" data-price-bar="{{ $index + 1 }}" data-count="{{ $bar['count'] }}" class="flex h-full flex-1 items-end">
                                    @php($step = intdiv($bar['count'] * 10 + $tallest - 1, $tallest))
                                    <span aria-hidden="true" class="w-full rounded-t-sm {{ $step === 0 ? 'h-0.5 bg-line' : 'bg-primary-600/45 '.$heights[$step - 1] }}"></span>
                                    <span class="sr-only">{{ $rupiah($bar['from']) }} sampai {{ $rupiah($bar['to']) }}: {{ $bar['count'] }} produk</span>
                                </li>
                            @endforeach
                        </ul>
                        <div aria-hidden="true" class="flex justify-between text-xs text-muted">
                            <span>{{ $rupiah($priceBars[0]['from']) }}</span>
                            <span>{{ $rupiah($priceBars[array_key_last($priceBars)]['to']) }}</span>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" min="0" step="1000" inputmode="numeric" wire:model.live.debounce.500ms="minPrice" placeholder="Min" aria-label="Harga minimum" class="h-12 w-full rounded-full border-[1.5px] border-line-strong bg-white px-4 text-base placeholder:text-muted focus:border-primary-600 focus:outline-none">
                        <input type="number" min="0" step="1000" inputmode="numeric" wire:model.live.debounce.500ms="maxPrice" placeholder="Maks" aria-label="Harga maksimum" class="h-12 w-full rounded-full border-[1.5px] border-line-strong bg-white px-4 text-base placeholder:text-muted focus:border-primary-600 focus:outline-none">
                    </div>
                </fieldset>

                <fieldset class="flex flex-col gap-2" x-data="{ error: '' }">
                    <legend class="mb-1.5 text-sm font-bold">Jarak</legend>
                    <button
                        id="toko-terdekat"
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-full border-2 border-ink px-5 text-sm font-bold hover:bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                        x-on:click="
                            if (! navigator.geolocation) { error = 'Browser ini tidak bisa membagikan lokasi.'; return; }
                            navigator.geolocation.getCurrentPosition(
                                (position) => { error = ''; $wire.locate(position.coords.latitude, position.coords.longitude) },
                                () => { error = 'Izin lokasi ditolak. Aktifkan lokasi di browser untuk melihat toko terdekat.' },
                                { timeout: 10000, maximumAge: 600000 },
                            )
                        "
                    >
                        {{ $located ? 'Perbarui lokasi saya' : 'Bagikan lokasi' }}
                    </button>
                    <p x-show="error" x-text="error" class="text-xs font-medium text-danger-700" x-cloak></p>

                    <div @class(['grid grid-cols-5 gap-1 rounded-full bg-subtle p-1', 'opacity-50' => ! $located])>
                        @foreach (['' => 'Semua'] + array_combine($radii, array_map(fn (int $km) => $km.' km', $radii)) as $km => $label)
                            <label wire:key="radius-{{ $km }}" class="flex h-10 items-center justify-center rounded-full text-sm font-semibold has-checked:bg-white has-checked:shadow-sm has-enabled:cursor-pointer has-focus-visible:outline-3 has-focus-visible:outline-primary-600">
                                <input type="radio" name="radius" value="{{ $km }}" data-radius-option="{{ $km }}" wire:model.live="radius" class="sr-only" @disabled(! $located)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-muted">
                        {{ $located ? 'Lokasi Anda hanya dipakai untuk menghitung jarak dan tidak disimpan.' : 'Bagikan lokasi untuk memilih jarak dari lapak.' }}
                    </p>
                </fieldset>

                @if ($storeOptions !== [])
                    <fieldset class="flex flex-col gap-1">
                        <legend class="mb-1.5 text-sm font-bold">Toko</legend>
                        <ul class="-mx-1 flex max-h-72 flex-col overflow-y-auto px-1">
                            @foreach ($storeOptions as $option)
                                <li wire:key="store-{{ $option['store']->id }}">
                                    <label data-store-option="{{ $option['store']->slug }}" data-count="{{ $option['count'] }}" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-option px-2 hover:bg-subtle has-focus-visible:outline-3 has-focus-visible:outline-primary-600">
                                        <input type="checkbox" wire:click="toggleStore({{ \Illuminate\Support\Js::from($option['store']->slug) }})" @checked($option['checked']) class="size-4.5 shrink-0 accent-primary-700 focus:outline-none">
                                        <span class="flex min-w-0 flex-1 flex-col py-1">
                                            <span class="truncate">{{ $option['store']->name }}</span>
                                            @if ($option['store']->is_demo)
                                                <span class="text-xs text-muted">Toko contoh</span>
                                            @endif
                                        </span>
                                        <span class="text-sm text-muted tabular-nums">{{ $option['count'] }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif
            </div>

            <div class="flex gap-3 border-t border-line p-4 lg:hidden">
                <button
                    type="button"
                    data-reset-filters
                    @if ($filtered) wire:click="clearFilters" @else disabled @endif
                    class="h-12 flex-1 rounded-full border-2 border-ink font-bold disabled:border-line-strong disabled:text-muted"
                >Atur ulang</button>
                <button type="button" x-on:click="close()" class="h-12 flex-[2] rounded-full bg-ink px-4 font-bold text-white">
                    Tampilkan {{ $products->total() }} produk
                </button>
            </div>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col gap-4" aria-label="Daftar produk">
            <div class="sticky top-0 z-30 -mx-4 flex items-center justify-between gap-3 bg-surface px-4 py-2 lg:static lg:mx-0 lg:p-0">
                <button
                    type="button"
                    x-ref="saring"
                    aria-controls="panel-filter"
                    aria-expanded="false"
                    x-bind:aria-expanded="open"
                    x-on:click="open = true; $nextTick(() => $refs.tutup.focus())"
                    class="inline-flex h-11 shrink-0 items-center gap-2 rounded-full border-2 border-ink bg-white px-4 text-sm font-bold lg:hidden"
                >
                    Saring
                    @if (count($activeFilters) > 0)
                        <span class="flex size-6 items-center justify-center rounded-full bg-ink text-xs text-white">{{ count($activeFilters) }}</span>
                    @endif
                </button>
                <p class="min-w-0 text-sm text-muted">
                    <span class="font-bold text-ink">{{ $products->total() }} produk</span>
                    <span wire:loading role="status" class="ml-2">Memuat produk…</span>
                </p>
                <label class="flex shrink-0 items-center gap-2 text-sm">
                    <span class="sr-only font-bold sm:not-sr-only">Urutkan</span>
                    <select wire:model.live="sort" class="h-11 rounded-full border-[1.5px] border-line-strong bg-white px-4 text-sm focus:border-primary-600 focus:outline-none">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($activeFilters !== [])
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <ul aria-label="Filter aktif" class="flex flex-wrap gap-2">
                        @foreach ($activeFilters as $filter)
                            <li wire:key="filter-{{ $filter['name'] }}">
                                <button
                                    type="button"
                                    wire:click="{{ "removeFilter('".$filter['name']."')" }}"
                                    aria-label="Hapus filter {{ $filter['label'] }}"
                                    class="inline-flex h-9 max-w-64 items-center gap-1.5 rounded-full bg-ink pr-2.5 pl-3.5 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                >
                                    <span class="truncate">{{ $filter['label'] }}</span>
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="clearFilters" class="min-h-9 text-sm font-semibold text-primary-700 underline underline-offset-4">Hapus semua filter</button>
                </div>
            @endif

            @if ($products->isEmpty())
                <x-empty-state
                    title="Produk tidak ditemukan"
                    :text="$q !== '' ? 'Tidak ada produk yang cocok dengan “'.$q.'”. Coba kata lain atau hapus filter.' : 'Coba ubah atau hapus filter.'"
                >
                    @if ($filtered && $activeFilters === [])
                        <x-button variant="secondary" wire:click="clearFilters" class="mt-2">Hapus semua filter</x-button>
                    @endif
                </x-empty-state>
            @else
                <ul class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:gap-x-5 lg:gap-y-7 xl:grid-cols-4" wire:loading.class="opacity-60">
                    @foreach ($products as $product)
                        <li wire:key="product-{{ $product->id }}">
                            <x-product-card :product="$product" :distance="$located ? $product->distance_km : null" />
                        </li>
                    @endforeach
                </ul>

                {{ $products->links('pagination', ['livewire' => true]) }}
            @endif
        </section>
    </div>
</div>
