<x-layouts.app title="Sesi kedaluwarsa" :bare="true" :noindex="true">
    <x-error-page
        code="419"
        title="Halaman ini sudah terlalu lama dibuka"
        body="Demi keamanan, formulir yang dibiarkan terbuka lama perlu dimuat ulang sebelum dikirim. Isian Anda belum terkirim, jadi tidak ada yang berubah."
        steps-title="Cara melanjutkan"
        :steps="['Muat ulang halaman ini.', 'Isi ulang formulirnya, lalu kirim lagi.', 'Kalau terus muncul, keluar lalu masuk kembali ke akun Anda.']"
    >
        @include('errors.partials.actions', ['reload' => 'Muat ulang halaman'])
    </x-error-page>
</x-layouts.app>
