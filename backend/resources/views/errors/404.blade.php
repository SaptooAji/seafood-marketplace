{{-- Only the category counts and the recently checked products query; both drop out when the database is down. --}}
<x-layouts.app title="Halaman tidak ditemukan" :bare="true" :noindex="true">
    <x-error-page
        code="404"
        :counted="true"
        title="Halaman ini tidak ada di pasar kami"
        body="Mungkin tautannya salah ketik atau produknya sudah tidak dijual. Cari langsung barang yang Anda butuhkan, atau mulai dari kategori di bawah."
    >
        {{-- The phone already has the search in the header. --}}
        <form action="{{ route('catalog') }}" method="GET" role="search" class="mt-1 hidden h-15 max-w-xl items-center gap-2.5 rounded-full border-2 border-ink bg-white pr-2 pl-5 focus-within:border-primary-600 lg:flex">
            <label for="cari-galat" class="sr-only">Cari produk</label>
            <svg class="size-5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input
                id="cari-galat"
                type="search"
                name="q"
                placeholder="Cari kepiting, cumi, kerang"
                maxlength="{{ \App\Livewire\Catalog::MAX_QUERY_LENGTH }}"
                class="min-w-0 flex-1 bg-transparent text-[1.0625rem] text-ink placeholder:text-muted focus:outline-none"
            >
            <button type="submit" class="h-11 shrink-0 rounded-full bg-primary-700 px-5.5 font-bold text-white hover:bg-primary-800">Cari</button>
        </form>
        <a href="{{ url('/') }}" class="flex min-h-11 items-center self-start font-semibold text-primary-700 underline underline-offset-4 lg:hidden">Kembali ke beranda</a>

        <x-slot:after>
            <x-recently-checked-products />
        </x-slot:after>
    </x-error-page>
</x-layouts.app>
