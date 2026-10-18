{{-- No queries: a rate-limited visitor must not add load. --}}
<x-layouts.app title="Terlalu banyak permintaan" :bare="true" :noindex="true">
    <x-error-page
        code="429"
        title="Terlalu banyak permintaan dalam waktu singkat"
        body="Untuk menjaga situs tetap lancar, kami membatasi berapa kali sebuah halaman bisa dibuka atau dikirim dalam satu menit. Tunggu sebentar, lalu coba lagi."
        steps-title="Yang bisa Anda lakukan"
        :steps="['Tunggu sekitar satu menit.', 'Hindari menekan tombol kirim berkali-kali.', 'Setelah itu buka lagi halamannya seperti biasa.']"
    >
        @include('errors.partials.actions', ['reload' => 'Coba lagi'])
    </x-error-page>
</x-layouts.app>
