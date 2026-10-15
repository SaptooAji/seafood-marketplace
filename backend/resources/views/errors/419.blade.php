<x-layouts.app title="Sesi kedaluwarsa" :bare="true" :noindex="true">
    <x-empty-state
        title="Sesi kedaluwarsa"
        text="Halaman ini terlalu lama dibuka. Muat ulang halaman, lalu coba lagi."
        action="Kembali ke beranda"
        :href="url('/')"
        class="mx-auto my-10 max-w-xl"
    />
</x-layouts.app>
