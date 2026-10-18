@props(['code', 'title', 'body', 'stepsTitle' => null, 'steps' => [], 'counted' => false, 'after' => null])

{{--
    The body of an error page: the code, the title, what happened, the
    actions (the slot), an optional light-green panel of numbered steps,
    and photo tiles of the categories from config/error-pages.php, with
    their product counts when `counted`. Static otherwise: no queries, and
    never the exception or the requested URL (DATA-5). `after` goes under
    the tiles.
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

    <x-error-category-tiles :counted="$counted" />

    {{ $after }}
</div>
