<div>
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into < and >, so seller text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-label="Foto produk" x-data="{ active: 0 }">
            @if ($product->images->isEmpty())
                <div class="flex aspect-square w-full items-center justify-center rounded-lg bg-subtle text-sm text-muted">Belum ada foto</div>
            @else
                @foreach ($product->images as $index => $image)
                    <img
                        src="{{ $image->url() }}"
                        alt="{{ $product->name }}"
                        x-show="active === {{ $index }}"
                        @if ($index > 0) x-cloak loading="lazy" @endif
                        class="aspect-square w-full rounded-lg object-cover"
                    >
                @endforeach

                @if ($product->images->count() > 1)
                    <div class="mt-3 flex gap-2 overflow-x-auto">
                        @foreach ($product->images as $index => $image)
                            <button type="button" x-on:click="active = {{ $index }}" class="shrink-0 overflow-hidden rounded-md border-2" x-bind:class="active === {{ $index }} ? 'border-primary-600' : 'border-transparent'">
                                <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}, foto {{ $index + 1 }}" loading="lazy" class="size-16 object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </section>

        <section class="flex flex-col gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
                <p class="mt-1 text-sm text-muted">
                    {{ $product->condition->label() }} &middot; {{ $product->sales_mode->label() }}
                </p>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-sm font-medium">Pilih varian</legend>
                @foreach ($this->variants as $variant)
                    <label wire:key="variant-{{ $variant->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border bg-white p-3 has-[:checked]:border-primary-600">
                        <input type="radio" wire:model.live="variantId" value="{{ $variant->id }}" class="mt-1">
                        <span class="flex-1">
                            <span class="block font-medium">{{ $variant->name }}</span>
                            <x-price :amount="$variant->price" :unit="$variant->unit->label()" class="block text-sm" />
                            <span class="block text-xs text-muted">
                                Stok {{ \App\Livewire\ProductPage::quantity($variant->stock) }} {{ $variant->unit->label() }}
                                &middot;
                                @if ($variant->isStockStale())
                                    <span class="font-medium text-warning-700">Stok belum dikonfirmasi</span>
                                @else
                                    Stok diperbarui {{ $variant->stock_confirmed_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB
                                @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </fieldset>

            @if ($this->selectedVariant)
                <div class="flex flex-col gap-1">
                    <label for="qty" class="text-sm font-medium">Jumlah ({{ $this->selectedVariant->unit->label() }})</label>
                    <input id="qty" type="text" inputmode="decimal" wire:model.live.debounce.400ms="qty" class="w-40 rounded-lg border border-line-strong px-3 py-2">
                    <p class="text-xs text-muted">Minimal {{ \App\Livewire\ProductPage::quantity($this->selectedVariant->min_qty) }} {{ $this->selectedVariant->unit->label() }}</p>
                    @error('qty')
                        <p class="text-xs text-danger-700">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <button type="button" disabled class="cursor-not-allowed rounded-lg bg-subtle px-4 py-2 text-muted">
                    Tambah ke keranjang &middot; Segera hadir
                </button>
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-success-700 px-4 py-2 font-medium text-white hover:bg-success-800">
                    Tanya lewat WhatsApp
                </a>
            </div>

            @if ($product->sales_mode === \App\Enums\SalesMode::PreOrder)
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm">
                    <p class="font-medium">Pre Order</p>
                    <ul class="mt-1 list-inside list-disc text-ink">
                        <li>Siap dikirim {{ $product->lead_time_days }} hari setelah pesanan</li>
                        <li>Kuota harian {{ \App\Livewire\ProductPage::quantity((string) $product->daily_quota) }}</li>
                        <li>Pesan sebelum pukul {{ substr((string) $product->cutoff_time, 0, 5) }} WIB</li>
                    </ul>
                </div>
            @endif

            <div class="rounded-lg border border-line bg-white p-3 text-sm">
                <p class="text-muted">Dijual oleh</p>
                <a href="{{ route('stores.show', $product->store->slug) }}" class="font-medium text-primary-700 hover:underline">{{ $product->store->name }}</a>
                <p class="text-muted">{{ $product->store->city }}</p>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="text-lg font-semibold">Deskripsi</h2>
            <p class="mt-2 whitespace-pre-line text-ink">{{ $product->description }}</p>

            <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                @if ($product->shelf_life_days)
                    <dt class="text-muted">Masa simpan</dt>
                    <dd>{{ $product->shelf_life_days }} hari</dd>
                @endif
                @if ($product->storage_instructions)
                    <dt class="text-muted">Cara penyimpanan</dt>
                    <dd>{{ $product->storage_instructions }}</dd>
                @endif
                @foreach ($product->attributeValues as $value)
                    <dt class="text-muted">{{ $value->categoryAttribute->name }}</dt>
                    <dd>{{ $value->value }}{{ $value->categoryAttribute->unit ? ' '.$value->categoryAttribute->unit : '' }}</dd>
                @endforeach
            </dl>
        </section>

        @if ($qualityStandard)
            <section>
                <h2 class="text-lg font-semibold">Standar kualitas</h2>
                {{-- Rendered by Str::markdown with raw HTML escaped and unsafe links dropped. --}}
                <div class="mt-2 space-y-2 text-ink">{!! $qualityStandard !!}</div>
            </section>
        @endif
    </div>
</div>
