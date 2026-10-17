<div class="flex flex-col gap-8 lg:gap-12">
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into < and >, so seller text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    @php
        $category = $product->category;
        $selected = $this->selectedVariant;
        $method = $this->selectedShippingMethod;
        $unit = $selected ? mb_strtolower($selected->unit->label()) : '';
        $photoCount = $product->images->count();
        $hasDetails = $product->shelf_life_days || filled($product->storage_instructions) || $product->attributeValues->isNotEmpty();
        $preOrder = $product->sales_mode === \App\Enums\SalesMode::PreOrder;
        $rupiah = fn (int $amount): string => 'Rp'.number_format($amount, 0, ',', '.');
        $tabs = array_filter([
            'deskripsi' => 'Deskripsi',
            'standar' => $qualityStandard ? 'Standar kualitas' : null,
            'rincian' => $hasDetails ? 'Rincian' : null,
        ]);
        $step = 0;
    @endphp

    {{-- The white band runs the full width of the window behind the top of the page. --}}
    <div class="-mt-6 flex flex-col gap-5 bg-white pt-6 pb-8 [box-shadow:0_0_0_100vmax_#fff] [clip-path:inset(0_-100vmax)] lg:pb-12">
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

        {{-- On a phone the steps come right after the photos; from lg the tabs sit under the photos. --}}
        <div class="grid gap-8 lg:grid-cols-[5fr_7fr] lg:grid-rows-[auto_1fr] lg:gap-x-12">
            {{--
                Board 4B: from lg the main photo with the next two as tiles beside it;
                on a phone one photo with previous and next buttons. The list comes in
                through a data attribute, never an inline script. Photos never change
                with the choices, so Livewire leaves Alpine's state alone.
            --}}
            @php($photoList = $product->images->map(fn ($image) => ['url' => $image->url(), 'thumb' => $image->thumbnailUrl()])->values()->all())
            <section
                aria-label="Foto produk"
                wire:ignore
                data-photos="{{ json_encode($photoList, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) }}"
                x-data="{
                    photos: JSON.parse($el.dataset.photos),
                    active: 0,
                    at(offset) { return (this.active + offset) % this.photos.length },
                    move(step) { this.active = (this.active + step + this.photos.length) % this.photos.length },
                }"
                @class([
                    'min-w-0 lg:col-start-1 lg:row-start-1',
                    'lg:grid lg:h-[460px] lg:grid-cols-[2fr_1fr] lg:grid-rows-2 lg:gap-2.5' => $photoCount > 1,
                ])
            >
                @if ($photoCount === 0)
                    <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-feature" />
                @else
                    <div @class(['relative overflow-hidden rounded-feature bg-subtle', 'lg:row-span-2' => $photoCount > 1])>
                        <img
                            src="{{ $photoList[0]['url'] }}"
                            x-bind:src="photos[active].url"
                            alt="{{ $product->name }}, foto 1 dari {{ $photoCount }}"
                            x-bind:alt="$el.dataset.name + ', foto ' + (active + 1) + ' dari ' + photos.length"
                            data-name="{{ $product->name }}"
                            @class(['aspect-square w-full object-cover', 'lg:aspect-auto lg:h-full' => $photoCount > 1])
                        >

                        @if ($photoCount > 1)
                            <button type="button" x-on:click="move(-1)" aria-label="Foto sebelumnya" class="absolute top-1/2 left-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink focus-visible:outline-3 focus-visible:outline-primary-600 lg:hidden">
                                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                            </button>
                            <button type="button" x-on:click="move(1)" aria-label="Foto berikutnya" class="absolute top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink focus-visible:outline-3 focus-visible:outline-primary-600 lg:hidden">
                                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                            <span aria-hidden="true" x-text="(active + 1) + ' dari ' + photos.length" class="absolute right-3 bottom-3 rounded-full bg-white/90 px-3 py-1 text-xs font-bold text-ink">1 dari {{ $photoCount }}</span>
                        @endif
                    </div>

                    @foreach (range(1, min(2, $photoCount - 1)) as $offset)
                        @continue($photoCount < 2)
                        <button
                            type="button"
                            data-photo-tile
                            aria-label="Tampilkan foto {{ $offset + 1 }} dari {{ $photoCount }}"
                            x-bind:aria-label="'Tampilkan foto ' + (at({{ $offset }}) + 1) + ' dari ' + photos.length"
                            x-on:click="active = at({{ $offset }})"
                            @class(['relative hidden overflow-hidden rounded-photo bg-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 lg:block', 'lg:row-span-2' => $photoCount === 2])
                        >
                            <img src="{{ $photoList[$offset]['thumb'] }}" x-bind:src="photos[at({{ $offset }})].thumb" alt="" loading="lazy" class="size-full object-cover">
                            @if ($offset === 2 && $photoCount > 3)
                                <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center bg-ink/45 text-lg font-bold text-white">+{{ $photoCount - 3 }} foto</span>
                            @endif
                        </button>
                    @endforeach
                @endif
            </section>

            <section aria-label="Pilihan pembelian" class="flex min-w-0 flex-col gap-6 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                <div class="flex flex-col gap-2.5">
                    <h1 class="text-[2rem] leading-[1.05] font-extrabold tracking-tight lg:text-[2.5rem]">{{ $product->name }}</h1>
                    <p class="text-muted">Dari {{ $product->store->name }}, {{ $product->store->city }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge>{{ $product->condition->label() }}</x-badge>
                        <x-badge :variant="$preOrder ? 'neutral' : 'success'">{{ $product->sales_mode->label() }}</x-badge>
                        @if ($checkedAt)
                            <span class="text-sm text-muted">Dicek {{ $checkedAt }}</span>
                        @elseif ($selected)
                            <x-badge variant="warning">Stok belum dikonfirmasi</x-badge>
                        @endif
                    </div>
                    @if ($selected)
                        <x-price :amount="$selected->price" :unit="$selected->unit->label()" class="text-[1.75rem] [&>span:last-child]:text-base" />
                    @endif
                    @if ($deadline)
                        <p class="rounded-option bg-accent-50 px-4 py-2.5 text-sm font-semibold text-accent-700">
                            Pesan sebelum {{ $deadline }}, dikirim {{ $deliveryDates[0]->locale('id')->translatedFormat('l') }}
                        </p>
                    @endif
                </div>

                <ol aria-label="Atur pesanan" class="flex flex-col">
                    <li class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-4">
                        <span aria-hidden="true" class="flex flex-col items-center">
                            <span data-step="{{ ++$step }}" class="flex size-9 items-center justify-center rounded-full bg-primary-700 font-bold text-white">{{ $step }}</span>
                            <span class="mt-1.5 w-0.5 flex-1 bg-primary-100 [li:last-child_&]:hidden"></span>
                        </span>
                        <div class="flex min-w-0 flex-col gap-3 pt-1 pb-7">
                        <h2 class="text-lg font-semibold">Pilih ukuran</h2>
                        <fieldset class="grid gap-2.5 sm:grid-cols-2">
                            <legend class="sr-only">Pilih ukuran</legend>
                            @foreach ($this->variants as $variant)
                                @php($available = in_array($variant->id, $availableIds, true))
                                <label wire:key="variant-{{ $variant->id }}" @class([
                                    'flex items-start gap-3 rounded-option border-2 border-line px-4 py-3.5 has-checked:border-primary-700 has-checked:bg-primary-50 has-focus-visible:outline-3 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary-600',
                                    'cursor-pointer hover:border-line-strong' => $available,
                                    'cursor-not-allowed opacity-60' => ! $available,
                                ])>
                                    <input type="radio" name="varian" wire:model.live="variantId" value="{{ $variant->id }}" @disabled(! $available) class="mt-0.5 size-5 shrink-0 accent-primary-700 focus-visible:outline-none">
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="font-bold">{{ $variant->name }}</span>
                                        <x-price :amount="$variant->price" :unit="$variant->unit->label()" />
                                        <span class="text-sm text-muted">
                                            @if ($preOrder)
                                                Dibuat sesuai pesanan
                                            @elseif ($available)
                                                Sisa {{ \App\Livewire\ProductPage::quantity($variant->stock) }} {{ $variant->unit->label() }}
                                            @else
                                                <span class="font-semibold text-danger-700">Stok habis</span>
                                            @endif
                                        </span>
                                        @if ($variant->isStockStale())
                                            <x-badge variant="warning" class="mt-1 self-start">Stok belum dikonfirmasi</x-badge>
                                        @else
                                            <span class="text-xs text-muted">Stok diperbarui {{ $variant->stock_confirmed_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        </div>
                    </li>

                    <li class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-4">
                        <span aria-hidden="true" class="flex flex-col items-center">
                            <span data-step="{{ ++$step }}" class="flex size-9 items-center justify-center rounded-full bg-primary-700 font-bold text-white">{{ $step }}</span>
                            <span class="mt-1.5 w-0.5 flex-1 bg-primary-100 [li:last-child_&]:hidden"></span>
                        </span>
                        <div class="flex min-w-0 flex-col gap-3 pt-1 pb-7">
                        <h2 class="text-lg font-semibold">{{ $selected && $selected->unit === \App\Enums\Unit::Kg ? 'Berapa kilo?' : 'Berapa '.($unit ?: 'banyak').'?' }}</h2>
                        @if ($soldOut)
                            <p class="font-semibold text-danger-700">Stok habis</p>
                        @endif
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <div class="inline-flex items-center rounded-full border-[1.5px] border-line-strong">
                                <button type="button" wire:click="decrement" @disabled($soldOut || $qty === $minQty) aria-label="Kurangi jumlah" class="flex size-12 items-center justify-center rounded-full text-xl font-bold hover:bg-subtle disabled:text-line-strong disabled:hover:bg-transparent focus-visible:outline-3 focus-visible:outline-primary-600">−</button>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    wire:model.blur="qty"
                                    value="{{ \App\Livewire\ProductPage::quantity($qty) }}"
                                    aria-label="Jumlah ({{ $selected?->unit->label() }})"
                                    @disabled($soldOut)
                                    class="h-12 w-20 bg-transparent text-center text-lg font-bold tabular-nums focus:outline-none"
                                >
                                <button type="button" wire:click="increment" @disabled($soldOut || $qty === $maxQty) aria-label="Tambah jumlah" class="flex size-12 items-center justify-center rounded-full text-xl font-bold hover:bg-subtle disabled:text-line-strong disabled:hover:bg-transparent focus-visible:outline-3 focus-visible:outline-primary-600">+</button>
                            </div>
                            @if ($selected && ! $soldOut)
                                <p class="text-sm text-muted">
                                    {{ $unit }}, kelipatan {{ \App\Livewire\ProductPage::quantity($selected->min_qty) }}, paling banyak {{ \App\Livewire\ProductPage::quantity((string) $maxQty) }}
                                </p>
                            @endif
                        </div>
                        </div>
                    </li>

                    @if ($preOrder)
                        <li class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-4">
                            <span aria-hidden="true" class="flex flex-col items-center">
                                <span data-step="{{ ++$step }}" class="flex size-9 items-center justify-center rounded-full bg-primary-700 font-bold text-white">{{ $step }}</span>
                                <span class="mt-1.5 w-0.5 flex-1 bg-primary-100 [li:last-child_&]:hidden"></span>
                            </span>
                            <div class="flex min-w-0 flex-col gap-3 pt-1 pb-7">
                            <h2 class="text-lg font-semibold">Kapan mau diterima?</h2>
                            <fieldset class="grid grid-cols-3 gap-2">
                                <legend class="sr-only">Kapan mau diterima?</legend>
                                @foreach ($deliveryDates as $date)
                                    <label wire:key="tanggal-{{ $date->format('Y-m-d') }}" class="flex cursor-pointer flex-col rounded-option border-2 border-line px-3 py-2.5 hover:border-line-strong has-checked:border-primary-700 has-checked:bg-primary-50 has-disabled:cursor-not-allowed has-disabled:opacity-60 has-focus-visible:outline-3 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary-600">
                                        <input type="radio" name="tanggal-kirim" value="{{ $date->format('Y-m-d') }}" data-delivery-date="{{ $date->format('Y-m-d') }}" wire:model.live="deliveryDate" @disabled($soldOut) class="sr-only">
                                        <span class="font-bold">{{ $date->locale('id')->translatedFormat('l') }}</span>
                                        <span class="text-sm">{{ $date->locale('id')->translatedFormat('j M') }}</span>
                                        @if ($product->daily_quota !== null)
                                            <span class="mt-1 text-xs text-muted">Kuota {{ \App\Livewire\ProductPage::quantity((string) $product->daily_quota) }} {{ $unit }} per hari</span>
                                        @endif
                                    </label>
                                @endforeach
                            </fieldset>
                            </div>
                        </li>
                    @endif

                    <li class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-4">
                        <span aria-hidden="true" class="flex flex-col items-center">
                            <span data-step="{{ ++$step }}" class="flex size-9 items-center justify-center rounded-full bg-primary-700 font-bold text-white">{{ $step }}</span>
                            <span class="mt-1.5 w-0.5 flex-1 bg-primary-100 [li:last-child_&]:hidden"></span>
                        </span>
                        <div class="flex min-w-0 flex-col gap-3 pt-1 pb-7">
                        <h2 class="text-lg font-semibold">Diantar atau ambil sendiri?</h2>
                        @if ($this->shippingMethods->isEmpty())
                            <p class="rounded-option bg-subtle px-4 py-3 text-sm">Tanyakan cara kirim ke penjual.</p>
                        @else
                            <fieldset class="grid gap-2.5 sm:grid-cols-2">
                                <legend class="sr-only">Diantar atau ambil sendiri?</legend>
                                @foreach ($this->shippingMethods as $option)
                                    <label wire:key="cara-{{ $option->id }}" class="flex cursor-pointer items-center gap-3 rounded-option border-2 border-line px-4 py-3 hover:border-line-strong has-checked:border-primary-700 has-checked:bg-primary-50 has-disabled:cursor-not-allowed has-disabled:opacity-60 has-focus-visible:outline-3 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary-600">
                                        <input type="radio" name="cara-terima" value="{{ $option->id }}" wire:model.live="shippingMethodId" @disabled($soldOut) class="size-5 accent-primary-700 focus-visible:outline-none">
                                        <span class="flex-1 font-semibold">{{ $option->type->label() }}</span>
                                        <span class="text-sm text-muted">
                                            @if ($fees[$option->id] === null)
                                                Dihitung saat checkout
                                            @elseif ($fees[$option->id] === 0)
                                                Gratis
                                            @else
                                                {{ $rupiah($fees[$option->id]) }}
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endif
                        </div>
                    </li>
                </ol>

                {{-- Stays in view at the bottom of the window while the steps scroll. --}}
                <section aria-labelledby="ringkasan-judul" class="sticky bottom-0 z-20 -mx-4 flex flex-col gap-3 border-t border-line bg-white px-4 py-4 shadow-[0_-8px_24px_-12px_rgb(27_46_37/0.25)] lg:mx-0 lg:rounded-card lg:border lg:p-5">
                    <h2 id="ringkasan-judul" class="sr-only">Ringkasan pesanan</h2>
                    @if ($selected)
                        <dl class="hidden grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm lg:grid">
                            <dt class="text-muted">Ukuran</dt>
                            <dd class="truncate font-semibold">{{ $selected->name }}</dd>
                            <dt class="text-muted">Jumlah</dt>
                            <dd class="font-semibold">{{ \App\Livewire\ProductPage::quantity($qty) }} {{ $unit }}</dd>
                            @if ($chosenDate)
                                <dt class="text-muted">Tanggal kirim</dt>
                                <dd class="font-semibold">{{ $chosenDate->locale('id')->translatedFormat('l, j M') }}</dd>
                            @endif
                            <dt class="text-muted">Cara terima</dt>
                            <dd class="font-semibold">
                                {{ $method?->type->label() ?? 'Belum dipilih' }}
                                @if ($method && $fees[$method->id] === null)
                                    <span class="font-normal text-muted">, ongkir dihitung saat checkout</span>
                                @endif
                            </dd>
                        </dl>
                    @endif
                    <p class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-muted">Total sementara</span>
                        <span data-order-total="{{ $total ?? 0 }}" class="text-2xl font-extrabold tracking-tight tabular-nums">{{ $rupiah($total ?? 0) }}</span>
                    </p>
                    <div class="flex flex-wrap gap-2.5">
                        <button type="button" disabled class="flex min-h-13 flex-[1_1_12rem] cursor-not-allowed flex-col items-center justify-center rounded-full bg-line-strong px-5 leading-tight font-bold text-white">
                            <span>Tambah ke keranjang</span>
                            <span class="text-xs font-semibold">Segera hadir</span>
                        </button>
                        @if ($whatsappUrl)
                            <x-button variant="secondary" :href="$whatsappUrl" target="_blank" rel="noopener noreferrer" class="min-h-13 flex-[1_1_10rem]">
                                Tanya lewat WhatsApp
                            </x-button>
                        @endif
                    </div>
                </section>

                <a href="{{ route('stores.show', $product->store->slug) }}" class="group flex items-center gap-3.5 rounded-option border-2 border-line px-4 py-3.5 hover:border-line-strong focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                    @if ($product->store->logoUrl())
                        <img src="{{ $product->store->logoUrl() }}" alt="" class="size-13 shrink-0 rounded-photo object-cover">
                    @else
                        <span class="flex size-13 shrink-0 items-center justify-center rounded-photo bg-primary-50 text-xl font-extrabold text-primary-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->store->name, 0, 1)) }}</span>
                    @endif
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="text-xs text-muted">Dijual oleh</span>
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="truncate font-bold">{{ $product->store->name }}</span>
                            @if ($product->store->is_demo)
                                <x-badge variant="demo" class="shrink-0">Toko contoh</x-badge>
                            @endif
                        </span>
                        <span class="truncate text-sm text-muted">{{ $product->store->city }}</span>
                    </span>
                    <span class="shrink-0 text-sm font-semibold text-primary-700 group-hover:underline">Lihat toko</span>
                </a>
            </section>

            {{-- Tabs follow the ARIA pattern: arrows move between tabs, only the chosen tab is in the tab order. --}}
            <div
                x-data="{ tab: 0, count: {{ count($tabs) }}, go(index) { this.tab = (index + this.count) % this.count; this.$nextTick(() => this.$refs['tab' + this.tab].focus()) } }"
                wire:ignore
                class="flex min-w-0 flex-col gap-4 lg:col-start-1 lg:row-start-2"
            >
                <div role="tablist" aria-label="Tentang produk" x-on:keydown.right.prevent="go(tab + 1)" x-on:keydown.left.prevent="go(tab - 1)" class="flex gap-1 overflow-x-auto border-b border-line">
                    @foreach (array_values($tabs) as $index => $label)
                        @php($key = array_keys($tabs)[$index])
                        <button
                            type="button"
                            role="tab"
                            id="tombol-{{ $key }}"
                            x-ref="tab{{ $index }}"
                            aria-controls="tab-{{ $key }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            tabindex="{{ $index === 0 ? '0' : '-1' }}"
                            x-bind:aria-selected="tab === {{ $index }} ? 'true' : 'false'"
                            x-bind:tabindex="tab === {{ $index }} ? 0 : -1"
                            x-on:click="tab = {{ $index }}"
                            class="-mb-px shrink-0 border-b-3 border-transparent px-3 py-2.5 font-semibold text-muted aria-selected:border-ink aria-selected:text-ink focus-visible:outline-3 focus-visible:outline-primary-600"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @foreach (array_keys($tabs) as $index => $key)
                    <div role="tabpanel" id="tab-{{ $key }}" aria-labelledby="tombol-{{ $key }}" tabindex="0" @if ($index > 0) hidden @endif x-bind:hidden="tab !== {{ $index }}" class="focus-visible:outline-3 focus-visible:outline-primary-600">
                        @if ($key === 'deskripsi')
                            <p class="max-w-prose leading-relaxed whitespace-pre-line">{{ $product->description }}</p>
                        @elseif ($key === 'standar')
                            {{-- Rendered by Str::markdown with raw HTML escaped and unsafe links dropped. --}}
                            <div class="max-w-prose space-y-2 leading-relaxed">{!! $qualityStandard !!}</div>
                        @else
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
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- An island renders once with the page and is skipped when a variant is chosen, so these lists are not looked up again. --}}
    @island(name: 'terkait')
        <div class="flex flex-col gap-10 lg:gap-12">
            @if ($this->similarProducts->isNotEmpty())
                <section aria-labelledby="produk-serupa" class="flex flex-col gap-4">
                    <h2 id="produk-serupa" class="text-[1.375rem] font-extrabold tracking-tight lg:text-[1.75rem]">Produk serupa</h2>
                    <ul class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4 lg:gap-x-5">
                        @foreach ($this->similarProducts as $similar)
                            <li wire:key="serupa-{{ $similar->id }}">
                                <x-product-card :product="$similar" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($this->otherStores->isNotEmpty())
                <section aria-labelledby="lapak-lain" class="flex flex-col gap-4">
                    <h2 id="lapak-lain" class="text-[1.375rem] font-extrabold tracking-tight lg:text-[1.75rem]">Lapak lain yang menjual {{ $product->category->name }}</h2>
                    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
                        @foreach ($this->otherStores as $other)
                            <li wire:key="lapak-{{ $other->id }}">
                                <a href="{{ route('stores.show', $other->slug) }}" class="group flex items-center gap-3.5 rounded-card bg-white p-3.5 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                    @if ($other->logoUrl())
                                        <img src="{{ $other->logoUrl() }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-photo object-cover">
                                    @else
                                        <span class="flex size-16 shrink-0 items-center justify-center rounded-photo bg-primary-50 text-2xl font-extrabold text-primary-700" aria-hidden="true">{{ mb_strtoupper(mb_substr($other->name, 0, 1)) }}</span>
                                    @endif
                                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span class="truncate font-bold group-hover:text-primary-700">{{ $other->name }}</span>
                                        <span class="truncate text-sm text-muted">{{ $other->city }}</span>
                                    </span>
                                    <span class="flex shrink-0 flex-col items-end text-sm">
                                        <span class="text-xs text-muted">Mulai</span>
                                        <x-price :amount="$other->from_price" :unit="$other->from_unit->label()" />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    @endisland
</div>
