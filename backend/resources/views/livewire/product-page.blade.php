<div class="flex flex-col gap-8 lg:gap-12">
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into < and >, so seller text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    @php
        $category = $product->category;
        $selected = $this->selectedVariant;
        $photoCount = $product->images->count();
        $hasDetails = $product->shelf_life_days || filled($product->storage_instructions) || $product->attributeValues->isNotEmpty();
    @endphp

    <div class="flex flex-col gap-5">
        <nav aria-label="Breadcrumb" class="text-sm text-muted">
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <li><a href="{{ route('home') }}" class="text-primary-700 hover:underline">Beranda</a></li>
                @if ($category->parent)
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('catalog', ['kategori' => $category->parent->slug]) }}" class="text-primary-700 hover:underline">{{ $category->parent->name }}</a></li>
                @endif
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('catalog', ['kategori' => $category->slug]) }}" class="text-primary-700 hover:underline">{{ $category->name }}</a></li>
                <li aria-hidden="true">/</li>
                <li><span aria-current="page" class="text-ink">{{ $product->name }}</span></li>
            </ol>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-10">
            {{-- Photos never change with the variant, so Livewire leaves Alpine's state alone. --}}
            <section aria-label="Foto produk" x-data="{ active: 0 }" wire:ignore class="flex flex-col gap-3 lg:grid lg:grid-cols-[5.5rem_minmax(0,1fr)] lg:items-start lg:gap-3">
                @if ($photoCount === 0)
                    <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-feature lg:col-span-2" />
                @else
                    <div class="relative overflow-hidden rounded-feature bg-white lg:col-start-2 lg:row-start-1">
                        @foreach ($product->images as $index => $image)
                            <img
                                src="{{ $image->url() }}"
                                alt="{{ $product->name }}"
                                x-show="active === {{ $index }}"
                                @if ($index > 0) x-cloak loading="lazy" @endif
                                class="aspect-square w-full object-cover"
                            >
                        @endforeach

                        @if ($photoCount > 1)
                            <span class="absolute right-3 bottom-3 rounded-full bg-white/90 px-3 py-1 text-xs font-bold text-ink" aria-hidden="true">
                                <span x-text="active + 1">1</span> dari {{ $photoCount }}
                            </span>
                        @endif
                    </div>

                    @if ($photoCount > 1)
                        <div class="flex gap-2 overflow-x-auto lg:col-start-1 lg:row-start-1 lg:flex-col lg:overflow-visible">
                            @foreach ($product->images as $index => $image)
                                <button
                                    type="button"
                                    aria-label="Foto {{ $index + 1 }} dari {{ $photoCount }}"
                                    aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                                    x-bind:aria-pressed="active === {{ $index }} ? 'true' : 'false'"
                                    x-on:click="active = {{ $index }}"
                                    class="size-18 shrink-0 overflow-hidden rounded-photo border-3 border-transparent bg-white aria-pressed:border-primary-700 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 lg:size-22"
                                >
                                    <img src="{{ $image->thumbnailUrl() }}" alt="" loading="lazy" class="size-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
            </section>

            <section aria-label="Pilihan pembelian" class="flex min-w-0 flex-col gap-5">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <x-badge>{{ $product->condition->label() }}</x-badge>
                        <x-badge :variant="$product->sales_mode === \App\Enums\SalesMode::PreOrder ? 'neutral' : 'success'">{{ $product->sales_mode->label() }}</x-badge>
                    </div>
                    <h1 class="text-[2rem] leading-[1.05] font-extrabold tracking-tight lg:text-[2.5rem]">{{ $product->name }}</h1>
                    @if ($selected)
                        <x-price :amount="$selected->price" :unit="$selected->unit->label()" class="text-[1.75rem] [&>span:last-child]:text-base" />
                    @endif
                </div>

                <fieldset class="flex flex-col gap-2.5">
                    <legend class="mb-2.5 text-base font-bold">Pilih varian</legend>
                    @foreach ($this->variants as $variant)
                        <label wire:key="variant-{{ $variant->id }}" class="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1 rounded-option border-2 border-transparent bg-white px-4 py-3.5 has-checked:border-primary-700 has-focus-visible:outline-3 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary-600">
                            <input type="radio" name="varian" wire:model.live="variantId" value="{{ $variant->id }}" class="row-span-2 mt-0.5 size-5 accent-primary-700 focus-visible:outline-none">
                            <span class="font-bold">{{ $variant->name }}</span>
                            <x-price :amount="$variant->price" :unit="$variant->unit->label()" class="text-right" />
                            <span class="col-span-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                                <span>Sisa {{ \App\Livewire\ProductPage::quantity($variant->stock) }} {{ $variant->unit->label() }}</span>
                                @if ($variant->isStockStale())
                                    <x-badge variant="warning">Stok belum dikonfirmasi</x-badge>
                                @else
                                    <span>Stok diperbarui {{ $variant->stock_confirmed_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @if ($selected)
                    <x-input
                        name="qty"
                        :label="'Jumlah ('.$selected->unit->label().')'"
                        inputmode="decimal"
                        wire:model.live.debounce.400ms="qty"
                        :help="'Minimal '.\App\Livewire\ProductPage::quantity($selected->min_qty).' '.$selected->unit->label()"
                        class="w-40"
                    />
                @endif

                <div class="flex flex-wrap gap-3">
                    <x-button disabled class="min-h-13 flex-[1_1_14rem] flex-col gap-0 leading-tight">
                        <span>Tambah ke keranjang</span>
                        <span class="text-xs font-semibold">Segera hadir</span>
                    </x-button>
                    <x-button variant="secondary" :href="$whatsappUrl" target="_blank" rel="noopener noreferrer" class="min-h-13 flex-[1_1_11rem]">
                        Tanya lewat WhatsApp
                    </x-button>
                </div>

                @if ($product->sales_mode === \App\Enums\SalesMode::PreOrder)
                    <div class="rounded-option bg-white px-4 py-3.5 text-sm">
                        <p class="font-bold">Pre Order</p>
                        <ul class="mt-1.5 flex list-inside list-disc flex-col gap-0.5">
                            <li>Siap dikirim {{ $product->lead_time_days }} hari setelah pesanan</li>
                            <li>Kuota harian {{ \App\Livewire\ProductPage::quantity((string) $product->daily_quota) }}</li>
                            <li>Pesan sebelum pukul {{ substr((string) $product->cutoff_time, 0, 5) }} WIB</li>
                        </ul>
                    </div>
                @endif

                <a href="{{ route('stores.show', $product->store->slug) }}" class="group flex items-center gap-3.5 rounded-option bg-white px-4 py-3.5 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                    @if ($product->store->logoUrl())
                        <img src="{{ $product->store->logoUrl() }}" alt="" class="size-13 shrink-0 rounded-photo object-cover">
                    @else
                        <span class="flex size-13 shrink-0 items-center justify-center rounded-photo bg-primary-50 text-xl font-extrabold text-primary-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->store->name, 0, 1)) }}</span>
                    @endif
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="text-xs text-muted">Dijual oleh</span>
                        <span class="truncate font-bold">{{ $product->store->name }}</span>
                        <span class="truncate text-sm text-muted">{{ $product->store->city }}</span>
                    </span>
                    <span class="shrink-0 text-sm font-semibold text-primary-700 group-hover:underline">Lihat toko</span>
                </a>
            </section>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-10">
        <div class="flex flex-col gap-8">
            <section aria-labelledby="deskripsi" class="flex flex-col gap-2">
                <h2 id="deskripsi" class="text-[1.375rem] font-extrabold tracking-tight">Deskripsi</h2>
                <p class="max-w-prose leading-relaxed whitespace-pre-line">{{ $product->description }}</p>
            </section>

            @if ($qualityStandard)
                <section aria-labelledby="standar-kualitas" class="flex flex-col gap-2">
                    <h2 id="standar-kualitas" class="text-[1.375rem] font-extrabold tracking-tight">Standar kualitas</h2>
                    {{-- Rendered by Str::markdown with raw HTML escaped and unsafe links dropped. --}}
                    <div class="max-w-prose space-y-2 leading-relaxed">{!! $qualityStandard !!}</div>
                </section>
            @endif
        </div>

        @if ($hasDetails)
            <section aria-labelledby="rincian" class="flex flex-col gap-2">
                <h2 id="rincian" class="text-[1.375rem] font-extrabold tracking-tight">Rincian</h2>
                <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-5 gap-y-2">
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
        @endif
    </div>
</div>
