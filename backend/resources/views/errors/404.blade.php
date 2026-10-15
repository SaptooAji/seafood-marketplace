{{-- The bare layout runs no queries, so this page renders even when the database is the problem. --}}
<x-layouts.app title="Halaman tidak ditemukan" :bare="true" :noindex="true">
    <x-empty-state
        title="Halaman tidak ditemukan"
        text="Tautannya mungkin salah, atau produknya sudah tidak dijual. Coba cari dari katalog."
        action="Buka katalog"
        :href="route('catalog')"
        class="mx-auto my-10 max-w-xl"
    >
        <x-button :href="url('/')" variant="ghost">Kembali ke beranda</x-button>
    </x-empty-state>
</x-layouts.app>
