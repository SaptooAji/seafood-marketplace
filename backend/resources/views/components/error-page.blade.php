@props(['code', 'title', 'body', 'stepsTitle' => null, 'steps' => [], 'after' => null])

{{--
    The body of an error page: the code, the title, what happened, the
    actions (the slot), an optional light-green panel of numbered steps,
    and photo tiles of the categories from config/error-pages.php. Static on
    purpose: no queries, and no exception or requested URL (DATA-5). `after`
    goes under the tiles.
--}}
<div class="flex flex-col gap-10 pt-6 pb-10 lg:gap-14 lg:pt-12 lg:pb-14">
    <div @class(['grid items-start gap-8', 'lg:grid-cols-[minmax(0,7fr)_minmax(0,4fr)] lg:gap-14' => $steps !== []])>
        <section aria-labelledby="galat-judul" class="flex max-w-3xl flex-col gap-4 lg:gap-4.5">
            <p class="text-[0.9375rem] text-muted">Kode {{ $code }}</p>
            <h1 id="galat-judul" class="text-[2.25rem] leading-[1.05] font-medium tracking-[-0.03em] lg:text-[3.5rem] lg:leading-[1.03]">{{ $title }}</h1>
            <p class="max-w-[36em] text-[1.0625rem] leading-relaxed text-muted lg:text-[1.1875rem]">{{ $body }}</p>
            {{ $slot }}
        </section>

        @if ($steps !== [])
            <section aria-labelledby="galat-langkah" class="flex flex-col gap-3.5 rounded-feature bg-primary-50 p-6">
                <h2 id="galat-langkah" class="text-lg font-semibold">{{ $stepsTitle }}</h2>
                <ol class="flex flex-col gap-3 text-primary-800">
                    @foreach ($steps as $step)
                        <li data-error-step class="grid grid-cols-[2rem_minmax(0,1fr)] items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 items-center justify-center rounded-full bg-white font-extrabold text-primary-700">{{ $loop->iteration }}</span>
                            <span class="pt-1 leading-normal">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>

    <section aria-labelledby="galat-kategori" class="flex flex-col gap-4">
        <h2 id="galat-kategori" class="text-xl font-medium tracking-tight lg:text-2xl">Atau mulai dari kategori</h2>
        <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            @foreach (config()->array('error-pages.categories') as $tile)
                <li>
                    <a href="{{ route('catalog', ['kategori' => $tile['slug']]) }}" data-error-category class="group relative block h-31 overflow-hidden rounded-feature text-white focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:h-45">
                        <img src="{{ asset($tile['photo']) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition-transform group-hover:scale-105 motion-reduce:transition-none">
                        <span aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-ink/90 to-ink/0 to-65%"></span>
                        <span class="absolute bottom-3.5 left-4 text-lg font-semibold lg:bottom-4 lg:left-4.5 lg:text-[1.375rem]">{{ $tile['name'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{ $after }}
</div>
