<div class="flex flex-col gap-6 lg:flex-row">
    <aside class="lg:w-64 lg:shrink-0">
        <h1 class="text-xl font-semibold">Katalog</h1>

        <div class="mt-4 flex flex-col gap-3 text-sm">
            <label class="flex flex-col gap-1">
                <span class="text-muted">Cari</span>
                <input type="search" wire:model.live.debounce.400ms="q" maxlength="{{ \App\Livewire\Catalog::MAX_QUERY_LENGTH }}" placeholder="Nama produk" class="rounded-lg border border-line-strong px-3 py-2">
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-muted">Kategori</span>
                <select wire:model.live="category" class="rounded-lg border border-line-strong px-3 py-2">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $root)
                        <option value="{{ $root->slug }}">{{ $root->name }}</option>
                        @foreach ($root->children as $child)
                            <option value="{{ $child->slug }}">&nbsp;&nbsp;{{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-muted">Kondisi</span>
                <select wire:model.live="condition" class="rounded-lg border border-line-strong px-3 py-2">
                    <option value="">Semua kondisi</option>
                    @foreach ($conditions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-muted">Toko</span>
                <select wire:model.live="store" class="rounded-lg border border-line-strong px-3 py-2">
                    <option value="">Semua toko</option>
                    @foreach ($stores as $option)
                        <option value="{{ $option->slug }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>

            <div
                class="flex flex-col gap-1"
                x-data="{ error: '' }"
            >
                <span class="text-muted">Jarak</span>
                <button
                    type="button"
                    class="rounded-lg border border-primary-600 px-3 py-2 text-left text-primary-700 hover:bg-primary-50"
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
                <p x-show="error" x-text="error" class="text-xs text-danger-700" x-cloak></p>

                @if ($located)
                    <select wire:model.live="radius" aria-label="Radius" class="rounded-lg border border-line-strong px-3 py-2">
                        <option value="">Semua jarak</option>
                        @foreach ($radii as $km)
                            <option value="{{ $km }}">Dalam {{ $km }} km</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-muted">Lokasi Anda hanya dipakai untuk menghitung jarak dan tidak disimpan.</p>
                @endif
            </div>

            <fieldset class="flex flex-col gap-1">
                <legend class="text-muted">Harga (Rp)</legend>
                <div class="flex items-center gap-2">
                    <input type="number" min="0" step="1000" inputmode="numeric" wire:model.live.debounce.500ms="minPrice" placeholder="Min" aria-label="Harga minimum" class="w-full rounded-lg border border-line-strong px-3 py-2">
                    <span class="text-muted">&ndash;</span>
                    <input type="number" min="0" step="1000" inputmode="numeric" wire:model.live.debounce.500ms="maxPrice" placeholder="Maks" aria-label="Harga maksimum" class="w-full rounded-lg border border-line-strong px-3 py-2">
                </div>
            </fieldset>
        </div>
    </aside>

    <section class="flex-1" aria-label="Daftar produk">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-muted">{{ $products->total() }} produk</p>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-muted">Urutkan</span>
                <select wire:model.live="sort" class="rounded-lg border border-line-strong px-3 py-2">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($products->isEmpty())
            <div class="mt-6 rounded-lg border border-dashed border-line-strong bg-white p-8 text-center">
                <p class="font-medium">Produk tidak ditemukan</p>
                @if ($q !== '')
                    <p class="mt-1 text-sm text-muted">Tidak ada produk yang cocok dengan &ldquo;{{ $q }}&rdquo;. Coba kata lain atau hapus filter.</p>
                @else
                    <p class="mt-1 text-sm text-muted">Coba ubah atau hapus filter.</p>
                @endif
            </div>
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60">
                @foreach ($products as $product)
                    <x-product-card :product="$product" :distance="$located ? $product->distance_km : null" wire:key="product-{{ $product->id }}" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $products->links() }}
            </div>
        @endif
    </section>
</div>
