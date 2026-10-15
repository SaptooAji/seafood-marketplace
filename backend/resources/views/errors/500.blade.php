{{-- Generic on purpose (DATA-5): no exception, message, or path. The bare layout runs no queries. --}}
<x-layouts.app title="Terjadi kesalahan" :bare="true" :noindex="true">
    <x-empty-state
        title="Terjadi kesalahan"
        text="Ada masalah di sisi kami. Muat ulang halaman ini sebentar lagi."
        action="Kembali ke beranda"
        :href="url('/')"
        class="mx-auto my-10 max-w-xl"
    >
        <x-button :href="route('catalog')" variant="ghost">Buka katalog</x-button>
    </x-empty-state>
</x-layouts.app>
