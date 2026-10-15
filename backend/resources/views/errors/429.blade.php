<x-layouts.app title="Terlalu banyak permintaan" :bare="true" :noindex="true">
    <x-empty-state
        title="Terlalu banyak permintaan"
        text="Tunggu sebentar, lalu coba lagi."
        action="Kembali ke beranda"
        :href="url('/')"
        class="mx-auto my-10 max-w-xl"
    />
</x-layouts.app>
