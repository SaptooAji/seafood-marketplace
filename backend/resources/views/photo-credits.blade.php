<x-layouts.app
    title="Kredit foto"
    description="Sumber dan lisensi foto Wikimedia Commons yang dipakai untuk toko contoh dan resep di Seafood Marketplace."
>
    <div class="flex max-w-3xl flex-col gap-8 lg:gap-10">
        <div class="flex flex-col gap-3">
            <h1 class="text-[2rem] leading-tight font-medium tracking-tight lg:text-[2.75rem]">Kredit foto</h1>
            <p class="leading-relaxed text-muted">
                Foto toko contoh dan resep di situs ini berasal dari Wikimedia Commons. Foto berlisensi CC BY dan CC BY-SA dipakai dengan menyebut judul, pembuat, sumber, dan lisensinya. Toko contoh bukan toko sungguhan: nama, harga, dan stoknya hanya untuk memperlihatkan tampilan marketplace.
            </p>
        </div>

        @forelse ($groups as $id => $group)
            <section aria-labelledby="{{ $id }}" class="flex flex-col gap-3">
                <h2 id="{{ $id }}" class="text-[1.375rem] font-medium tracking-tight lg:text-[1.75rem]">{{ $group['heading'] }}</h2>

                <ul class="flex flex-col rounded-card bg-white px-4 lg:px-6">
                    @foreach ($group['credits'] as $credit)
                        <li class="flex gap-4 border-b border-line py-4 last:border-b-0">
                            @if ($credit['image'])
                                <img src="{{ $credit['image'] }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-photo bg-subtle object-cover">
                            @endif

                            <dl class="grid min-w-0 flex-1 grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
                                <dt class="text-muted">Judul</dt>
                                <dd class="min-w-0 break-words"><a href="{{ $credit['source_url'] }}" rel="noopener noreferrer" class="font-semibold text-primary-700 underline underline-offset-4">{{ $credit['title'] }}</a></dd>

                                <dt class="text-muted">Pembuat</dt>
                                <dd>{{ $credit['author'] }}</dd>

                                <dt class="text-muted">Lisensi</dt>
                                <dd>
                                    @if ($credit['license_url'])
                                        <a href="{{ $credit['license_url'] }}" rel="noopener noreferrer license" class="underline underline-offset-4">{{ $credit['license'] }}</a>
                                    @else
                                        {{ $credit['license'] }}
                                    @endif
                                </dd>

                                @if (filled($credit['changes'] ?? null))
                                    <dt class="text-muted">Perubahan</dt>
                                    <dd>{{ $credit['changes'] }}</dd>
                                @endif
                            </dl>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="rounded-card bg-white p-5">Belum ada foto yang perlu dikreditkan.</p>
        @endforelse
    </div>
</x-layouts.app>
