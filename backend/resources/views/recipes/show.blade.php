@php
    $servingsData = [
        'servings' => $recipe->servings,
        'ingredients' => array_map(fn (array $ingredient): array => $ingredient['amounts'], $recipe->ingredientsByServings()),
        'labels' => \App\Data\Recipe::byServings(fn (int $servings): string => $recipe->neededLabel($servings)),
        'totals' => $stores->map(fn (array $row): array => $row['totals'])->values()->all(),
    ];
@endphp

<x-layouts.app
    :title="$recipe->name"
    :description="$recipe->description"
    :image="asset($recipe->photo)"
>
    @push('head')
        {{-- JSON_HEX_TAG turns < and > into escapes, so recipe text cannot close this tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
    @endpush

    <div
        class="mx-auto flex max-w-5xl flex-col gap-5"
        data-recipe="{{ json_encode($servingsData, JSON_THROW_ON_ERROR) }}"
        x-data="{
            data: JSON.parse($el.dataset.recipe),
            porsi: {{ $recipe->servings }},
            shared: false,
            share() {
                if (navigator.share) { navigator.share({ title: document.title, url: location.href }); return; }
                navigator.clipboard.writeText(location.href).then(() => { this.shared = true });
            },
        }"
    >
        <nav aria-label="Breadcrumb" class="text-sm text-muted print:hidden">
            <a href="{{ route('recipes.index') }}" class="text-primary-700 hover:underline">Resep</a>
            <span aria-hidden="true">/</span>
            <span class="text-ink">{{ $recipe->name }}</span>
        </nav>

        <article aria-labelledby="judul" class="overflow-hidden rounded-feature bg-white">
            <div class="flex flex-col gap-5 p-5 lg:grid lg:grid-cols-[minmax(0,1fr)_18.75rem] lg:items-center lg:gap-10 lg:p-11">
                <img src="{{ asset($recipe->photo) }}" alt="{{ $recipe->name }}" class="size-50 self-center rounded-full bg-subtle object-cover lg:order-2 lg:size-75">

                <div class="flex flex-col gap-4">
                    <h1 id="judul" class="text-[2.125rem] leading-[1.05] font-medium tracking-tight lg:text-[3.375rem]">{{ $recipe->name }}</h1>
                    <p class="leading-relaxed text-muted lg:text-[1.0625rem]">{{ $recipe->description }}</p>
                    <dl class="flex">
                        <div class="pr-4 lg:pr-6"><dt class="text-xs text-muted lg:text-[0.8125rem]">Waktu</dt><dd class="mt-0.5 text-[1.0625rem] font-semibold lg:text-xl">{{ $recipe->minutes }} menit</dd></div>
                        <div class="border-l border-line px-4 lg:px-6"><dt class="text-xs text-muted lg:text-[0.8125rem]">Porsi</dt><dd class="mt-0.5 text-[1.0625rem] font-semibold lg:text-xl" x-text="porsi + ' orang'">{{ $recipe->servings }} orang</dd></div>
                        <div class="border-l border-line pl-4 lg:pl-6"><dt class="text-xs text-muted lg:text-[0.8125rem]">Tingkat</dt><dd class="mt-0.5 text-[1.0625rem] font-semibold lg:text-xl">{{ $recipe->level }}</dd></div>
                    </dl>
                    <div class="flex gap-2 print:hidden">
                        <button type="button" x-on:click="window.print()" class="h-11 flex-1 rounded-full border-2 border-ink px-5 text-sm font-bold hover:bg-surface lg:flex-none">Cetak</button>
                        <button type="button" x-on:click="share()" class="h-11 flex-1 rounded-full border-2 border-ink px-5 text-sm font-bold hover:bg-surface lg:flex-none">
                            <span x-show="! shared">Bagikan</span>
                            <span x-show="shared" x-cloak>Tautan disalin</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                <section aria-labelledby="bahan" class="flex flex-col gap-3.5 bg-primary-50 px-5 pt-6 pb-7 lg:py-8 lg:pr-9 lg:pl-11">
                    <h2 id="bahan" class="text-xl font-semibold lg:text-[1.375rem]">Bahan</h2>

                    <div class="flex items-center justify-between gap-3 rounded-option bg-white py-2.5 pr-2.5 pl-4 print:hidden">
                        <span class="font-semibold">Untuk</span>
                        <span class="flex items-center gap-1">
                            <button type="button" aria-label="Kurangi porsi" x-on:click="porsi = Math.max(1, porsi - 1)" x-bind:disabled="porsi <= 1" class="size-10 rounded-full bg-primary-50 text-xl font-bold disabled:opacity-40">−</button>
                            <span aria-live="polite" class="min-w-21 text-center font-bold"><span x-text="porsi">{{ $recipe->servings }}</span> porsi</span>
                            <button type="button" aria-label="Tambah porsi" x-on:click="porsi = Math.min(12, porsi + 1)" x-bind:disabled="porsi >= 12" class="size-10 rounded-full bg-primary-700 text-xl font-bold text-white disabled:opacity-40">+</button>
                        </span>
                    </div>

                    <ul class="flex flex-col gap-0.5">
                        @foreach ($recipe->ingredientsByServings() as $index => $ingredient)
                            <li class="flex items-baseline gap-2 py-1.5 text-[0.9375rem]">
                                <span>{{ $ingredient['name'] }}</span>
                                @if ($ingredient['amounts'] !== [])
                                    <span aria-hidden="true" class="flex-1 -translate-y-1 border-b-2 border-dotted border-line-strong"></span>
                                    <span class="font-bold whitespace-nowrap" x-text="data.ingredients[{{ $index }}][porsi]">{{ $ingredient['amounts'][$recipe->servings] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section aria-labelledby="cara" class="flex flex-col gap-3.5 px-5 pt-6 pb-7 lg:py-8 lg:pr-11 lg:pl-9">
                    <h2 id="cara" class="text-xl font-semibold lg:text-[1.375rem]">Cara memasak</h2>
                    <ol class="flex flex-col gap-4">
                        @foreach ($recipe->steps as $step)
                            <li class="grid grid-cols-[2.125rem_minmax(0,1fr)] gap-2.5 lg:grid-cols-[2.75rem_minmax(0,1fr)] lg:gap-3.5">
                                <span aria-hidden="true" class="text-[1.75rem] leading-none font-medium text-accent-500 lg:text-[2.125rem]">{{ $loop->iteration }}</span>
                                <span class="leading-relaxed lg:text-base">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>
        </article>

        <section aria-labelledby="beli" class="mt-4 flex flex-col gap-3.5 print:hidden lg:mt-7">
            <div class="flex flex-col gap-1">
                <h2 id="beli" class="text-[1.375rem] font-medium tracking-tight lg:text-[1.875rem]" x-text="'Beli ' + data.labels[porsi] + ' untuk resep ini'">Beli {{ $recipe->neededLabel($recipe->servings) }} untuk resep ini</h2>
                @if ($stores->isNotEmpty())
                    <p class="text-sm text-muted lg:text-base">Total sudah untuk <span x-text="porsi">{{ $recipe->servings }}</span> porsi. Lapak asli ditampilkan lebih dulu.</p>
                @endif
            </div>

            @if ($stores->isEmpty())
                <div class="flex flex-col gap-2 rounded-card bg-white p-5">
                    <p>Belum ada lapak yang menjual {{ $recipe->neededName() }} per kilo.</p>
                    <a href="{{ route('catalog', ['kategori' => $recipe->needs['category']]) }}" class="self-start font-semibold text-primary-700 underline underline-offset-4">Cari di katalog</a>
                </div>
            @else
                <ul class="grid gap-2.5 lg:grid-cols-3 lg:gap-4">
                    @foreach ($stores as $index => $row)
                        @php($store = $row['store'])
                        <li class="flex">
                            <a href="{{ route('stores.show', $store->slug) }}" class="flex flex-1 flex-col gap-2 rounded-card bg-white p-4 hover:ring-2 hover:ring-primary-600 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600 lg:p-4.5">
                                <span class="flex min-h-5.5 flex-wrap gap-1.5">
                                    @if ($row['cheapest'])
                                        <x-badge variant="warning">Termurah</x-badge>
                                    @endif
                                    @if ($store->is_demo)
                                        <x-badge variant="demo">Toko contoh</x-badge>
                                    @endif
                                </span>
                                <span class="text-[1.0625rem] font-semibold lg:text-lg">{{ $store->name }}</span>
                                <span class="text-[0.8125rem] text-muted">{{ $store->city }}. Sisa {{ \App\Data\Recipe::formatAmount((string) $store->stock_left) }} kg, dicek {{ $store->checked_at ? \App\Support\StockCheckTime::format($store->checked_at) : 'belum pernah' }}</span>
                                <span class="mt-auto pt-1">
                                    <span class="text-[1.375rem] font-extrabold tracking-tight lg:text-[1.625rem]" x-text="data.totals[{{ $index }}][porsi]">{{ $row['totals'][$recipe->servings] }}</span>
                                    <span class="text-xs text-muted">(<x-price :amount="$store->from_price" unit="kg" class="text-xs" />)</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($swaps !== [])
                <div class="mt-3 flex flex-col gap-2.5">
                    <h3 class="text-[1.0625rem] font-semibold lg:text-lg">Bisa juga diganti dengan</h3>
                    <ul class="grid gap-2.5 lg:grid-cols-2 lg:gap-3">
                        @foreach ($swaps as $swap)
                            <li>
                                <a href="{{ route('catalog', ['kategori' => $swap['slug']]) }}" class="flex items-center justify-between gap-3 rounded-card bg-white px-4 py-3.5 hover:ring-2 hover:ring-primary-600 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                    <span class="flex flex-col gap-0.5">
                                        <span class="font-semibold">{{ $swap['name'] }}</span>
                                        <span class="text-[0.8125rem] text-muted">Untuk {{ $recipe->servings }} porsi</span>
                                    </span>
                                    <span class="font-extrabold whitespace-nowrap">Mulai {{ $swap['price'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        @if ($others !== [])
            <section aria-labelledby="resep-lain" class="mt-4 flex flex-col gap-3.5 print:hidden lg:mt-7">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="resep-lain" class="text-[1.375rem] font-medium tracking-tight lg:text-[1.875rem]">Resep lain yang mirip</h2>
                    <a href="{{ route('recipes.index') }}" class="flex min-h-11 items-center text-sm font-semibold text-primary-700 underline-offset-4 hover:underline lg:text-base">Semua resep</a>
                </div>
                <ul class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 lg:mx-0 lg:grid lg:grid-cols-3 lg:gap-5 lg:overflow-visible lg:px-0">
                    @foreach ($others as $other)
                        <li class="flex w-55 shrink-0 snap-start lg:w-auto">
                            <a href="{{ route('recipes.show', $other->slug) }}" class="group flex flex-1 flex-col gap-1.5 rounded-card bg-white p-2 pb-3.5 focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:p-2.5 lg:pb-4">
                                <img src="{{ asset($other->photo) }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-photo bg-subtle object-cover">
                                <span class="mt-1 px-1 text-xs text-muted">{{ $other->meta() }}</span>
                                <span class="px-1 text-[1.0625rem] leading-tight font-semibold group-hover:text-primary-700 lg:text-xl">{{ $other->name }}</span>
                                <span class="mt-auto px-1 pt-1 text-[0.8125rem] font-semibold text-accent-700">Butuh {{ $other->needs['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
