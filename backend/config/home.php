<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Home Page
|--------------------------------------------------------------------------
|
| The highlights of the home hero, in order. Each links a product by slug;
| a highlight whose product buyers cannot see is skipped. `photo` is a path
| under public/, or null for the product's first photo. When every
| highlight is skipped, the hero shows `fallback_photo` without a product
| card. Kept in this shape so the super admin can manage it from `settings`
| later.
|
*/

return [

    'highlights' => [
        [
            'product' => 'kepiting-bakau-jumbo',
            'title' => 'Kepiting hidup, dipilih per ekor',
            'text' => 'Kepiting bakau jantan dua ekor per kilo, diikat dan dicek setiap pagi sebelum dijual.',
            'button' => 'Pilih kepiting',
            'photo' => null,
        ],
        [
            'product' => 'udang-vaname-size-40',
            'title' => 'Pesan hari ini, udang datang besok pagi',
            'text' => 'Udang tambak dipanen subuh dan diantar ke rumah. Kuota tiap hari terbatas.',
            'button' => 'Pesan udang',
            'photo' => null,
        ],
        [
            'product' => 'cumi-segar',
            'title' => 'Cumi segar dari kapal subuh',
            'text' => 'Mata masih jernih, daging kenyal. Bisa dibersihkan sesuai pesanan.',
            'button' => 'Lihat cumi',
            'photo' => null,
        ],
        [
            'product' => 'kerang-hijau-2',
            'title' => 'Kerang hijau langsung dari bagan',
            'text' => 'Dipanen subuh dan dijual per kilo atau per karung untuk acara.',
            'button' => 'Lihat kerang',
            'photo' => null,
        ],
    ],

    'fallback_photo' => 'images/resep/kepiting-saus-padang.jpg',

];
