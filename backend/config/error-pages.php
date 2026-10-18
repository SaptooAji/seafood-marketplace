<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Error Pages
|--------------------------------------------------------------------------
|
| The category photo tiles under "Atau mulai dari kategori" on the 404,
| 419, 429, and 500 pages. A static list, not the categories table: a 500
| can come from the database itself, and a 429 must not add load. Each
| `slug` is a root category; `photo` is a path under public/ credited in
| config/photo-credits.php.
|
*/

return [

    'categories' => [
        ['name' => 'Kepiting', 'slug' => 'kepiting', 'photo' => 'images/resep/kepiting-kukus-jahe.jpg'],
        ['name' => 'Udang', 'slug' => 'udang', 'photo' => 'images/resep/udang-goreng-mentega.jpg'],
        ['name' => 'Cumi', 'slug' => 'cumi', 'photo' => 'images/resep/cumi-saus-tiram.jpg'],
        ['name' => 'Kerang', 'slug' => 'kerang', 'photo' => 'images/resep/kerang-hijau-saus-padang.jpg'],
    ],

];
