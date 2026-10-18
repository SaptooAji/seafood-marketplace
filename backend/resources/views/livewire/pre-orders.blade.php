{{--
    After the "Pre Order Web 2" and "Pre Order Ponsel 2" boards. From lg the
    filters are a white sticky panel beside the list. On a phone the deadline
    options are a row of pills in a sticky bar, and the category and
    distance open as a sheet from "Saring"; Escape or the close button shuts
    it and focus goes back to "Saring". Ready stock checked today sits on a
    white band under the list.
--}}
@php
    $kg = fn (string $amount) => \App\Data\Recipe::formatAmount($amount);
    $km = fn (?float $distance) => $distance === null ? null : number_format($distance, 1, ',', '.').' km';
@endphp

<div
    x-data="{ open: false, close() { this.open = false; this.$nextTick(() => this.$refs.saring.focus()) } }"
    x-on:keydown.escape.window="if (open) close()"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
>
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 pt-6 pb-9 lg:grid lg:grid-cols-[17.5rem_minmax(0,1fr)] lg:items-start lg:gap-10 lg:pt-10 lg:pb-18">
        <div x-show="open" x-cloak x-on:click="close()" class="fixed inset-0 z-40 bg-ink/55 lg:hidden" aria-hidden="true"></div>

        <aside
            id="saring-pre-order"
            aria-labelledby="saring-pre-order-judul"
            data-open="false"
            x-bind:data-open="open"
            x-bind:role="open ? 'dialog' : null"
            x-bind:aria-modal="open ? 'true' : null"
            class="fixed inset-x-0 bottom-0 z-50 hidden max-h-[85dvh] flex-col rounded-t-feature bg-white data-[open=true]:flex lg:sticky lg:top-6 lg:z-auto lg:flex lg:max-h-none lg:rounded-feature"
        >
            <div class="flex items-center justify-between gap-3 px-4 pt-3 pb-2 lg:sr-only">
                <h2 id="saring-pre-order-judul" class="text-xl font-semibold">Saring</h2>
                <button type="button" x-ref="tutup" x-on:click="close()" aria-label="Tutup saring" class="flex size-11 items-center justify-center rounded-full bg-surface focus-visible:outline-3 focus-visible:outline-primary-600 lg:hidden">
                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="flex flex-col overflow-y-auto px-4 pb-2 lg:gap-5.5 lg:p-5.5">
                {{-- On a phone these are the pills above the list. --}}
                <fieldset class="hidden flex-col gap-2 lg:flex">
                    <legend class="mb-2 font-semibold">Dikirim paling lambat</legend>
                    @foreach ($deadlineOptions as $option)
                        <button
                            type="button"
                            wire:key="tenggat-{{ $option['value'] }}"
                            data-deadline-option="{{ $option['value'] }}"
                            data-count="{{ $option['count'] }}"
                            aria-pressed="{{ $deadline === $option['value'] ? 'true' : 'false' }}"
                            wire:click="chooseDeadline({{ \Illuminate\Support\Js::from($option['value']) }})"
                            class="flex min-h-12 items-center justify-between rounded-[0.875rem] border-2 border-line bg-white px-3.5 text-left font-semibold hover:bg-surface focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 aria-pressed:border-primary-700 aria-pressed:bg-primary-50"
                        >
                            {{ $option['label'] }}
                            <span class="font-medium text-muted tabular-nums">{{ $option['count'] }}</span>
                        </button>
                    @endforeach
                </fieldset>

                <fieldset class="flex flex-col gap-1 border-t border-line py-3.5 lg:border-0 lg:py-0">
                    <legend class="float-left mb-2 w-full font-semibold">Kategori</legend>
                    @foreach ($categoryOptions as $option)
                        <button
                            type="button"
                            wire:key="kategori-{{ $option['slug'] }}"
                            data-category-option="{{ $option['slug'] }}"
                            data-count="{{ $option['count'] }}"
                            aria-pressed="{{ $category === $option['slug'] ? 'true' : 'false' }}"
                            wire:click="chooseCategory({{ \Illuminate\Support\Js::from($option['slug']) }})"
                            class="group flex min-h-11 items-center justify-between rounded-[0.875rem] px-3.5 text-left font-semibold hover:bg-surface focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 aria-pressed:bg-ink aria-pressed:text-white"
                        >
                            {{ $option['name'] }}
                            <span class="font-medium opacity-75 tabular-nums">{{ $option['count'] }}</span>
                        </button>
                    @endforeach
                </fieldset>

                <fieldset class="flex flex-col gap-2 border-t border-line py-3.5 lg:border-0 lg:py-0">
                    <legend class="float-left mb-2 w-full font-semibold">Jarak</legend>
                    <div @class(['grid grid-cols-5 gap-1 rounded-full bg-subtle p-1', 'opacity-50' => ! $located])>
                        @foreach (['' => 'Semua'] + array_combine($radii, array_map(fn (int $km) => $km.' km', $radii)) as $value => $label)
                            <label wire:key="radius-{{ $value }}" class="flex h-10 items-center justify-center rounded-full text-sm font-semibold has-checked:bg-white has-checked:shadow-sm has-enabled:cursor-pointer has-focus-visible:outline-3 has-focus-visible:outline-primary-600">
                                <input type="radio" name="radius" value="{{ $value }}" data-radius-option="{{ $value }}" wire:model.live="radius" class="sr-only" @disabled(! $located)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-muted">
                        {{ $located ? 'Lokasi Anda hanya dipakai untuk menghitung jarak dan tidak disimpan.' : 'Bagikan lokasi untuk memilih jarak dari lapak.' }}
                    </p>
                </fieldset>
            </div>

            <div class="flex gap-2.5 border-t border-line px-4 pt-3 pb-5 lg:hidden">
                <button
                    type="button"
                    @if ($filtered) wire:click="clearFilters" @else disabled @endif
                    class="h-13 rounded-full border-2 border-ink px-4.5 font-bold disabled:border-line-strong disabled:text-muted"
                >Atur ulang</button>
                <button type="button" x-on:click="close()" class="h-13 flex-1 rounded-full bg-primary-700 px-4 text-[0.9375rem] font-bold whitespace-nowrap text-white">
                    Tampilkan {{ $preOrders->total() }} produk pre order
                </button>
            </div>
        </aside>

        <section aria-labelledby="pre-order-judul" class="flex min-w-0 flex-col gap-4 lg:col-start-2 lg:row-start-1 lg:gap-5">
            <div class="flex flex-col gap-2">
                <h1 id="pre-order-judul" class="scroll-mt-6 text-[2rem] leading-[1.05] font-medium tracking-[-0.025em] lg:text-[2.75rem] lg:tracking-[-0.027em]">Semua pre order</h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted lg:text-[0.9375rem]" x-data="{ error: '' }">
                    <svg class="size-4 shrink-0 lg:size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s-7-6.2-7-11.5A7 7 0 0 1 19 10.5C19 15.8 12 22 12 22z"/><circle cx="12" cy="10.5" r="2.5"/></svg>
                    <span>
                        @if (! $located)
                            Bagikan lokasi untuk melihat jarak setiap lapak
                        @elseif ($radiusKm !== null)
                            Lapak dalam {{ $radiusKm }} km dari lokasi Anda
                        @else
                            Jarak dihitung dari lokasi Anda
                        @endif
                    </span>
                    <button
                        type="button"
                        class="inline-flex min-h-8 items-center font-semibold text-primary-700 underline underline-offset-4"
                        x-on:click="
                            if (! navigator.geolocation) { error = 'Browser ini tidak bisa membagikan lokasi.'; return; }
                            navigator.geolocation.getCurrentPosition(
                                (position) => { error = ''; $wire.locate(position.coords.latitude, position.coords.longitude) },
                                () => { error = 'Izin lokasi ditolak. Aktifkan lokasi di browser untuk melihat jarak lapak.' },
                                { timeout: 10000, maximumAge: 600000 },
                            )
                        "
                    >{{ $located ? 'Ubah lokasi' : 'Bagikan lokasi' }}</button>
                    <p x-show="error" x-text="error" class="w-full text-xs font-medium text-danger-700" x-cloak></p>
                </div>
                @if ($orderBy !== null)
                    <p class="self-start rounded-xl bg-warning-50 px-3 py-2 text-sm font-bold text-warning-700 lg:hidden">Pesan sebelum {{ $orderBy }} untuk dikirim besok</p>
                @endif
            </div>

            {{-- Phone: the deadline pills, the count, and "Saring" stay in view. --}}
            <div class="sticky top-0 z-30 -mx-4 flex flex-col gap-2.5 bg-surface py-2.5 lg:hidden">
                <div role="group" aria-label="Dikirim paling lambat" class="flex gap-2 overflow-x-auto px-4 [scrollbar-width:none]">
                    @foreach ($deadlineOptions as $option)
                        <button
                            type="button"
                            wire:key="tenggat-ponsel-{{ $option['value'] }}"
                            aria-pressed="{{ $deadline === $option['value'] ? 'true' : 'false' }}"
                            wire:click="chooseDeadline({{ \Illuminate\Support\Js::from($option['value']) }})"
                            class="h-11 shrink-0 rounded-full border-2 border-line bg-white px-3.5 text-sm font-semibold whitespace-nowrap aria-pressed:border-primary-700 aria-pressed:bg-primary-50"
                        >{{ $option['label'] }} <span class="font-medium text-muted">{{ $option['count'] }}</span></button>
                    @endforeach
                </div>
                <div class="flex items-center justify-between gap-3 px-4">
                    <p class="font-semibold">{{ $preOrders->total() }} produk pre order <span wire:loading role="status" class="ml-1 text-sm font-normal text-muted">Memuat…</span></p>
                    <button
                        type="button"
                        x-ref="saring"
                        aria-controls="saring-pre-order"
                        aria-expanded="false"
                        x-bind:aria-expanded="open"
                        x-on:click="open = true; $nextTick(() => $refs.tutup.focus())"
                        class="inline-flex h-11 items-center gap-2 rounded-full border-2 border-ink bg-white px-4 text-sm font-bold"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                        Saring
                        @if ($category !== '' || $radiusKm !== null)
                            <span class="flex size-5 items-center justify-center rounded-full bg-ink text-xs text-white">{{ ($category !== '' ? 1 : 0) + ($radiusKm !== null ? 1 : 0) }}</span>
                        @endif
                    </button>
                </div>
            </div>

            <div class="hidden flex-wrap justify-between gap-2 font-semibold lg:flex">
                <p>{{ $preOrders->total() }} produk pre order <span wire:loading role="status" class="ml-2 text-sm font-normal text-muted">Memuat produk…</span></p>
                @if ($orderBy !== null)
                    <p class="text-warning-700">Pesan sebelum {{ $orderBy }} untuk dikirim besok</p>
                @endif
            </div>

            @if (! $hasAny)
                <x-empty-state
                    title="Belum ada produk pre order"
                    text="Lihat produk yang siap dikirim di katalog."
                    action="Lihat katalog"
                    :href="route('catalog')"
                />
            @elseif ($preOrders->isEmpty())
                <x-empty-state title="Tidak ada pre order untuk pilihan ini" text="Coba hari kirim yang lebih lama, kategori lain, atau jarak yang lebih jauh.">
                    <x-button variant="secondary" wire:click="clearFilters" class="mt-2">Hapus saringan</x-button>
                </x-empty-state>
            @else
                <ul class="grid grid-cols-2 gap-3 lg:grid-cols-3 lg:gap-5" wire:loading.class="opacity-60">
                    @foreach ($preOrders as $item)
                        @php
                            $product = $item['product'];
                            $image = $product->images->first();
                            $cheapest = $product->variants->first();
                        @endphp
                        <li wire:key="pre-order-{{ $product->id }}" data-pre-order-card="{{ $product->name }}" class="flex">
                            <a href="{{ route('products.show', $product->slug) }}" class="group flex flex-1 flex-col gap-1.5 rounded-[1.25rem] bg-white p-2 pb-3 focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:gap-2 lg:rounded-card lg:p-2.5 lg:pb-4">
                                <span class="relative block">
                                    @if ($image)
                                        <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full rounded-[0.875rem] bg-subtle object-cover lg:aspect-4/3 lg:rounded-photo">
                                    @else
                                        <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-[0.875rem] lg:aspect-4/3 lg:rounded-photo" />
                                    @endif
                                    <span class="absolute top-1.5 left-1.5 rounded-full bg-ink px-2 py-0.5 text-[0.6875rem] font-semibold whitespace-nowrap text-white lg:top-2 lg:left-2 lg:px-2.5 lg:text-xs">Paling cepat {{ $item['earliest']->locale('id')->translatedFormat('D j') }}</span>
                                </span>
                                <span class="flex flex-wrap items-center gap-x-1.5 gap-y-1 px-0.5 text-[0.6875rem] text-muted lg:px-1 lg:text-[0.8125rem]">
                                    {{ implode(', ', array_filter([$product->store->name, $km($item['distance'])])) }}
                                    @if ($product->store->is_demo)
                                        <x-badge variant="demo">Toko contoh</x-badge>
                                    @endif
                                </span>
                                <span class="px-0.5 text-[0.9375rem] leading-tight font-semibold group-hover:text-primary-700 lg:px-1 lg:text-[1.0625rem]">{{ $product->name }}</span>
                                @if ($cheapest)
                                    <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="px-0.5 text-[1.0625rem] lg:px-1 lg:text-[1.1875rem]" />
                                @endif
                                @if ($product->daily_quota !== null)
                                    <span class="mt-auto px-0.5 pt-0.5 text-xs font-semibold text-primary-800 lg:px-1 lg:text-[0.8125rem]">Kuota {{ $kg((string) $product->daily_quota) }} kg per hari</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{ $preOrders->links('pagination', ['livewire' => true, 'scrollTo' => "document.getElementById('pre-order-judul').scrollIntoView()"]) }}
            @endif
        </section>
    </div>

    @if ($ready->isNotEmpty())
        <section aria-labelledby="siap-judul" class="bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-3.5 py-8 lg:gap-5 lg:px-4 lg:pt-14 lg:pb-16">
                <div class="flex flex-wrap items-end justify-between gap-4 px-4 lg:px-0">
                    <div class="flex flex-col gap-1">
                        <h2 id="siap-judul" class="text-[1.375rem] leading-tight font-medium tracking-tight lg:text-[1.875rem]">Tidak mau menunggu? Ini stok yang ada hari ini</h2>
                        <p class="text-sm leading-normal text-muted lg:text-base">Bukan pre order. Stoknya sudah di lapak dan dicek pagi ini<span class="hidden lg:inline">, jadi bisa diantar hari ini atau diambil sendiri</span>.</p>
                    </div>
                    <a href="{{ route('catalog') }}" class="hidden min-h-11 items-center font-semibold text-primary-700 underline underline-offset-4 lg:flex">Lihat katalog</a>
                </div>

                <ul class="flex gap-3 overflow-x-auto px-4 [scrollbar-width:none] lg:grid lg:grid-cols-4 lg:gap-5 lg:overflow-visible lg:px-0">
                    @foreach ($ready as $product)
                        @php
                            $image = $product->images->first();
                            $cheapest = $product->variants->first();
                        @endphp
                        <li data-ready-card="{{ $product->name }}" class="flex w-44 shrink-0 lg:w-auto">
                            <a href="{{ route('products.show', $product->slug) }}" class="group flex flex-1 flex-col gap-1.25 rounded-[1.25rem] bg-surface p-2 pb-3 focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:gap-1.5 lg:rounded-card lg:p-2.5 lg:pb-4">
                                <span class="relative block">
                                    @if ($image)
                                        <img src="{{ $image->thumbnailUrl() }}" alt="{{ $product->name }}" loading="lazy" class="aspect-square w-full rounded-[0.875rem] bg-subtle object-cover lg:rounded-photo">
                                    @else
                                        <x-empty-state compact title="Belum ada foto" class="aspect-square w-full rounded-[0.875rem] lg:rounded-photo" />
                                    @endif
                                    <span class="absolute top-1.5 left-1.5 rounded-full bg-primary-700 px-2 py-0.5 text-[0.6875rem] font-semibold whitespace-nowrap text-white lg:top-2 lg:left-2 lg:px-2.5 lg:text-xs">Siap hari ini</span>
                                </span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-1.5 px-0.5 text-[0.6875rem] text-muted lg:mt-1 lg:px-1 lg:text-xs">
                                    <span class="rounded-full bg-subtle px-2 py-px font-semibold text-ink">{{ $product->condition->label() }}</span>
                                    {{ implode(', ', array_filter(['Dicek '.\App\Support\StockCheckTime::format($product->last_checked_at), $cheapest ? 'sisa '.$kg((string) $cheapest->stock).' '.\Illuminate\Support\Str::lower($cheapest->unit->label()) : null])) }}
                                </span>
                                <span class="px-0.5 text-[0.9375rem] leading-tight font-semibold group-hover:text-primary-700 lg:px-1 lg:text-[1.0625rem]">{{ $product->name }}</span>
                                <span class="flex flex-wrap items-center gap-x-1.5 gap-y-1 px-0.5 text-[0.6875rem] text-muted lg:px-1 lg:text-[0.8125rem]">
                                    {{ implode(', ', array_filter([$product->store->name, $located ? $km((float) $product->getAttribute('distance_km')) : null])) }}
                                    @if ($product->store->is_demo)
                                        <x-badge variant="demo">Toko contoh</x-badge>
                                    @endif
                                </span>
                                @if ($cheapest)
                                    <x-price :amount="$cheapest->price" :unit="$cheapest->unit->label()" class="mt-auto px-0.5 pt-0.5 text-[1.0625rem] lg:px-1 lg:pt-1 lg:text-[1.1875rem]" />
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('catalog') }}" class="mx-4 flex h-12 items-center justify-center rounded-full border-2 border-ink font-bold lg:hidden">Lihat katalog</a>
            </div>
        </section>
    @endif
</div>
