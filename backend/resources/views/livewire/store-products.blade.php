<section aria-labelledby="produk-toko" class="flex min-w-0 flex-col gap-5 lg:col-start-2 lg:row-start-1">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="produk-toko" class="scroll-mt-6 text-[1.5rem] font-medium tracking-tight lg:text-[1.875rem]">Produk di lapak ini</h2>
        <p wire:loading role="status" class="text-sm text-muted">Memuat produk…</p>
    </div>

    @if ($tabs !== [])
        <nav aria-label="Kategori di lapak ini" class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <ul class="flex min-w-max gap-6 border-b border-line">
                <li wire:key="tab-semua">
                    <a href="{{ route('stores.show', $store->slug) }}" wire:click.prevent="chooseCategory({{ \Illuminate\Support\Js::from('') }})" @if ($category === '') aria-current="page" @endif class="-mb-px flex h-12 items-center gap-1.5 border-b-3 border-transparent px-0.5 font-medium aria-[current=page]:border-ink aria-[current=page]:font-bold">Semua <span class="font-medium text-muted">{{ $total }}</span></a>
                </li>
                @foreach ($tabs as $tab)
                    <li wire:key="tab-{{ $tab['slug'] }}">
                        <a href="{{ route('stores.show', ['slug' => $store->slug, 'kategori' => $tab['slug']]) }}" wire:click.prevent="chooseCategory({{ \Illuminate\Support\Js::from($tab['slug']) }})" @if ($category === $tab['slug']) aria-current="page" @endif class="-mb-px flex h-12 items-center gap-1.5 border-b-3 border-transparent px-0.5 font-medium aria-[current=page]:border-ink aria-[current=page]:font-bold">{{ $tab['name'] }} <span class="font-medium text-muted">{{ $tab['count'] }}</span></a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @if ($products->isEmpty())
        <x-empty-state
            title="Toko ini belum punya produk yang dijual"
            text="Lihat produk dari toko lain di katalog."
            action="Lihat katalog"
            :href="route('catalog')"
        />
    @else
        <ul class="grid grid-cols-2 gap-x-3 gap-y-5 lg:grid-cols-3 lg:gap-x-5 lg:gap-y-7" wire:loading.class="opacity-60">
            @foreach ($products as $product)
                <li wire:key="produk-{{ $product->id }}">
                    <x-product-card :product="$product" />
                </li>
            @endforeach
        </ul>

        {{ $products->links('pagination', ['livewire' => true, 'scrollTo' => "document.getElementById('produk-toko').scrollIntoView()"]) }}
    @endif
</section>
