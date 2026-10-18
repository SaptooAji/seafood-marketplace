{{-- Generic on purpose (DATA-5): no exception, message, or path. No queries, so it renders when the database is the problem. --}}
<x-layouts.app title="Terjadi kesalahan" :bare="true" :noindex="true">
    <x-error-page
        code="500"
        title="Ada gangguan di server kami"
        body="Halaman gagal dimuat karena masalah di sisi kami, bukan karena Anda. Tim kami sudah menerima laporannya. Keranjang Anda tidak hilang."
        steps-title="Sambil menunggu"
        :steps="['Muat ulang halaman dalam beberapa menit.', 'Kalau sedang memesan, cek halaman Pesanan saya sebelum memesan ulang agar tidak dobel.', 'Gangguan biasanya pulih dalam waktu singkat.']"
    >
        @include('errors.partials.actions', ['reload' => 'Muat ulang'])
    </x-error-page>
</x-layouts.app>
