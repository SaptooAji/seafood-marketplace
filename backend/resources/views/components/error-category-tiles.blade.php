<section aria-labelledby="galat-kategori" class="flex flex-col gap-3 lg:gap-4">
    <h2 id="galat-kategori" class="text-xl font-medium tracking-tight lg:text-2xl">Atau mulai dari kategori</h2>
    <ul class="grid grid-cols-2 gap-2.5 lg:grid-cols-4 lg:gap-4">
        @foreach ($tiles as $tile)
            <li>
                <a href="{{ route('catalog', ['kategori' => $tile['slug']]) }}" data-error-category class="group relative block h-31 overflow-hidden rounded-[1.25rem] text-white focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:h-45 lg:rounded-feature">
                    <img src="{{ asset($tile['photo']) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition-transform group-hover:scale-105 motion-reduce:transition-none">
                    <span aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-ink/90 to-ink/0 to-70% lg:to-65%"></span>
                    <span class="absolute bottom-2.5 left-3 flex flex-col lg:bottom-4 lg:left-4.5">
                        <span class="text-[1.0625rem] font-semibold lg:text-[1.375rem]">{{ $tile['name'] }}</span>
                        @if ($tile['count'] !== null)
                            <span data-error-category-count class="text-xs text-white/85 lg:text-sm">{{ $tile['count'] }} produk</span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
