<div x-data="{ open: false }" class="flex flex-col gap-5">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-[1.75rem] leading-tight font-medium tracking-tight lg:text-[2.125rem]">Katalog</h1>

        {{-- On a phone the filters fold into a panel; from lg they are a sidebar. --}}
        <button
            type="button"
            aria-controls="panel-filter"
            aria-expanded="false"
            x-bind:aria-expanded="open"
            x-on:click="open = ! open"
            class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-ink px-4 text-sm font-bold lg:hidden"
        >
            Filter
            @if (count($activeFilters) > 0)
                <span class="flex size-6 items-center justify-center rounded-full bg-ink text-xs text-white">{{ count($activeFilters) }}</span>
            @endif
        </button>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8">
        <aside
            id="panel-filter"
            aria-label="Filter"
            data-open="false"
            x-bind:data-open="open"
            class="hidden rounded-card bg-white p-4 data-[open=true]:block lg:block lg:w-72 lg:shrink-0 lg:p-5"
        >
            <div class="flex flex-col gap-4">
                <x-input name="q" label="Cari" type="search" wire:model.live.debounce.400ms="q" maxlength="{{ \App\Livewire\Catalog::MAX_QUERY_LENGTH }}" placeholder="Nama produk" />

                <x-select name="category" label="Kategori" wire:model.live="category">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $root)
                        <option value="{{ $root->slug }}">{{ $root->name }}</option>
                        @foreach ($root->children as $child)
                            <option value="{{ $child->slug }}">&nbsp;&nbsp;{{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </x-select>

                <x-select name="condition" label="Kondisi" wire:model.live="condition">
                    <option value="">Semua kondisi</option>
                    @foreach ($conditions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-select>

                <x-select name="store" label="Toko" wire:model.live="store">
                    <option value="">Semua toko</option>
                    @foreach ($stores as $option)
                        <option value="{{ $option->slug }}">{{ $option->name }}</option>
                    @endforeach
                </x-select>

                <fieldset class="flex flex-col gap-1.5">
                    <legend class="mb-1.5 text-sm font-bold">Harga (Rp)</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" min="0" step="1000" inputmode="numeric" wire:model.live.debounce.500ms="minPrice" placeholder="Min" aria-label="Harga minimum" class="h-12 w-full rounded-full border-[1.5px] border-line-strong bg-white px-4 text-base placeholder:text-muted focus:border-primary-600 focus:outline-none">
                        <input type="number" min="0" step="1000" inputmode="numeric" wire:model.live.debounce.500ms="maxPrice" placeholder="Maks" aria-label="Harga maksimum" class="h-12 w-full rounded-full border-[1.5px] border-line-strong bg-white px-4 text-base placeholder:text-muted focus:border-primary-600 focus:outline-none">
                    </div>
                </fieldset>

                <div class="flex flex-col gap-1.5" x-data="{ error: '' }">
                    <span class="text-sm font-bold">Jarak</span>
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
                        {{ $located ? 'Perbarui lokasi saya' : 'Toko terdekat' }}
                    </button>
                    <p x-show="error" x-text="error" class="text-xs font-medium text-danger-700" x-cloak></p>

                    @if ($located)
                        <select wire:model.live="radius" aria-label="Radius" class="h-12 rounded-full border-[1.5px] border-line-strong bg-white px-4 text-base focus:border-primary-600 focus:outline-none">
                            <option value="">Semua jarak</option>
                            @foreach ($radii as $km)
                                <option value="{{ $km }}">Dalam {{ $km }} km</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-muted">Lokasi Anda hanya dipakai untuk menghitung jarak dan tidak disimpan.</p>
                    @endif
                </div>
            </div>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col gap-4" aria-label="Daftar produk">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted">
                    <span class="font-bold text-ink">{{ $products->total() }} produk</span>
                    <span wire:loading role="status" class="ml-2">Memuat produk…</span>
                </p>
                <label class="flex items-center gap-2 text-sm">
                    <span class="font-bold">Urutkan</span>
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
