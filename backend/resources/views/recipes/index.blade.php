<x-layouts.app
    title="Resep hasil laut"
    description="Resep kepiting, udang, cumi, dan kerang lengkap dengan takaran, waktu masak, dan berapa banyak hasil laut yang perlu dibeli."
    :full-width="true"
    :noindex="$term !== ''"
>
    <section aria-labelledby="judul-resep" class="bg-ink text-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 pt-8 pb-7 lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:items-center lg:gap-12 lg:py-14">
            <div class="flex flex-col gap-4">
                <h1 id="judul-resep" class="text-[2.125rem] leading-[1.05] font-medium tracking-tight lg:text-[3.25rem]">Mau masak apa hari ini?</h1>
                <p class="max-w-[34em] leading-relaxed text-white/85 lg:text-lg">Pilih menurut waktu dan keperluan Anda. Bahan lautnya bisa dibeli dari lapak terdekat.</p>
                <form action="{{ route('recipes.index') }}" method="GET" role="search" class="flex h-13 max-w-lg items-center gap-2 rounded-full bg-white pr-1.5 pl-4 lg:h-14">
                    <label for="q-resep" class="sr-only">Cari resep</label>
                    <svg class="size-4.5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="q-resep" type="search" name="q" value="{{ $term }}" maxlength="{{ \App\Http\Controllers\Web\RecipeController::MAX_QUERY_LENGTH }}" placeholder="Cari resep atau bahan" class="min-w-0 flex-1 bg-transparent text-base text-ink placeholder:text-muted focus:outline-none">
                    <button type="submit" class="h-10 shrink-0 rounded-full bg-primary-700 px-5 text-sm font-bold text-white hover:bg-primary-800">Cari</button>
                </form>
            </div>

            <nav aria-label="Lompat ke kelompok resep" class="grid grid-cols-2 gap-2 lg:gap-3">
                @foreach ($groups as $id => $group)
                    <a href="#resep-{{ $id }}" class="flex flex-col gap-0.5 rounded-option bg-white/10 p-3.5 hover:bg-white/15 focus-visible:outline-3 focus-visible:outline-white lg:p-4.5">
                        <span class="text-[0.9375rem] leading-snug font-semibold lg:text-lg">{{ $group['title'] }}</span>
                        <span class="text-xs text-white/75 lg:text-sm">{{ count($group['recipes']) }} resep</span>
                    </a>
                @endforeach
                <a href="{{ route('recipes.index') }}" class="flex flex-col gap-0.5 rounded-option bg-accent-500 p-3.5 hover:bg-accent-700 focus-visible:outline-3 focus-visible:outline-white lg:p-4.5">
                    <span class="text-[0.9375rem] font-semibold lg:text-lg">Semua resep</span>
                    <span class="text-xs text-white/85 lg:text-sm">{{ count(\App\Data\Recipe::all()) }} resep</span>
                </a>
            </nav>
        </div>
    </section>

    <div class="mx-auto flex max-w-6xl flex-col gap-10 px-4 pt-8 pb-12 lg:gap-14 lg:pt-14 lg:pb-16">
        @if ($term !== '')
            <p class="text-muted">Hasil untuk "<span class="font-semibold text-ink">{{ $term }}</span>": {{ $total }} resep</p>
        @endif

        @forelse ($groups as $id => $group)
            <section aria-labelledby="resep-{{ $id }}" class="flex flex-col gap-3 lg:gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="resep-{{ $id }}" class="scroll-mt-6 text-[1.375rem] font-medium tracking-tight lg:text-[1.875rem]">{{ $group['title'] }}</h2>
                    <p class="text-sm text-muted lg:text-base">{{ $group['note'] }}</p>
                </div>

                <ul class="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-5 lg:overflow-visible lg:px-0">
                    @foreach ($group['recipes'] as $recipe)
                        <li class="flex w-55 shrink-0 snap-start lg:w-auto">
                            <a href="{{ route('recipes.show', $recipe->slug) }}" class="group flex flex-1 flex-col gap-1.5 rounded-card bg-white p-2 pb-3.5 focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-primary-600 lg:p-2.5 lg:pb-4">
                                <img src="{{ asset($recipe->photo) }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-photo bg-subtle object-cover">
                                <span class="mt-1 px-1 text-xs text-muted lg:text-[0.8125rem]">{{ $recipe->meta() }}</span>
                                <span class="px-1 text-[1.0625rem] leading-tight font-semibold group-hover:text-primary-700 lg:text-xl">{{ $recipe->name }}</span>
                                <span class="mt-auto px-1 pt-1 text-[0.8125rem] font-semibold text-accent-700 lg:text-sm">Butuh {{ $recipe->needs['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <x-empty-state
                :title="'Belum ada resep untuk “'.$term.'”'"
                text="Coba kata lain, misalnya nama hasil laut atau bumbu."
                action="Lihat semua resep"
                :href="route('recipes.index')"
            />
        @endforelse
    </div>
</x-layouts.app>
