# Sistem desain: Bakau dan Kepiting

Identitas visual web pembeli dan aplikasi Android penjual. Dipilih lewat eksplorasi di kanvas desain (arah 2, palet 9c, latar pasir).

**Sumber kebenaran**
- `design/tokens.json`: warna, huruf, radius, jarak.
- Turunannya:
  - Web: `@theme` di `backend/resources/css/app.css`.
  - Android: `design/android/theme/*.kt` dan `design/android/res/values/colors.xml`.

Test `backend/tests/Feature/Web/VisualIdentityTest.php` gagal bila tabel warna di bawah, CSS, dan `tokens.json` tidak sama.

## Prinsip

- **Foto yang memberi warna.** Antarmuka tenang di atas pasir. Setiap produk tampil dengan minimal tiga foto.
- **Pakai peran, bukan nama warna.** Tulis `bg-primary-700` atau `text-muted`, jangan `bg-green-800`. Palet bawaan Tailwind dilarang di view pembeli (dicek test).
- **Hijau bakau untuk aksi, oranye kepiting untuk peringatan.**
  - Satu layar paling banyak punya satu tombol hijau penuh.
  - Pengecualian: di atas foto hero beranda, tombol utama berwarna putih dengan teks `primary-800`, karena tombol hijau tenggelam di atas foto yang gelap.
  - Oranye hanya dipakai untuk titik logo dan label stok, tidak pernah untuk tombol.
- **Kontras minimal WCAG AA:**
  - 4.5:1 untuk teks;
  - 3:1 untuk tepi kolom isian.

  Pasangan yang dipakai ada di tabel kontras dan dites.
- **Aset dari situs sendiri.** Font di-bundle Vite, sedangkan ikon dan pola ada di `public/`. Tidak ada CDN pihak ketiga (persiapan CSP, CONF-5).

## Warna

| Token | Hex | Kegunaan |
| --- | --- | --- |
| `primary-50` | `#E3EEE7` | Latar label positif ("Siap kirim hari ini") |
| `primary-100` | `#CFE3D7` | Sorotan lembut |
| `primary-600` | `#3A6E55` | Cincin fokus, tepi saat hover |
| `primary-700` | `#2F5D46` | Hijau bakau: tombol utama, pilihan aktif, tautan, `theme-color` |
| `primary-800` | `#244A38` | Tombol saat ditekan, teks di atas `primary-50` |
| `accent-50` | `#FBEEE5` | Latar peringatan stok |
| `accent-500` | `#D9733C` | Oranye kepiting: titik logo dan dekorasi, bukan teks |
| `accent-700` | `#97461C` | Teks peringatan stok |
| `ink` | `#1B2E25` | Teks utama, chip aktif, footer, label "Masuk 05.40" |
| `muted` | `#575C52` | Teks sekunder: satuan, kota, jarak |
| `surface` | `#F6F3EC` | Latar halaman (pasir) |
| `subtle` | `#ECE6DA` | Placeholder foto, tombol nonaktif |
| `line` | `#E5DFD3` | Garis pemisah, tepi header |
| `line-strong` | `#878C80` | Tepi kolom isian |
| `pattern` | `#DCCFB6` | Garis pola laut di atas pasir (cadangan, opsional) |
| `success-50` | `#E3EEE7` | Alias `primary-50` |
| `success-700` | `#2F5D46` | Alias `primary-700`: tombol WhatsApp dan konfirmasi |
| `success-800` | `#244A38` | Alias `primary-800` |
| `warning-50` | `#FBEEE5` | Alias `accent-50` |
| `warning-200` | `#E9C2A6` | Tepi kotak Pre Order |
| `warning-700` | `#97461C` | Alias `accent-700` |
| `danger-50` | `#FCE9E6` | Latar error dan stok habis |
| `danger-700` | `#A8321F` | Error validasi, stok habis |

Kartu, header, dan teks di atas tombol berwarna memakai putih (`#FFFFFF`).

## Pasangan kontras

| Teks | Latar | Minimal | Contoh |
| --- | --- | --- | --- |
| `ink` | putih, `surface` | 4.5 | Isi halaman |
| `muted` | putih, `surface`, `subtle` | 4.5 | Keterangan, satuan |
| `primary-700` | putih, `surface` | 4.5 | Tautan |
| putih | `primary-700`, `primary-800` | 4.5 | Tombol utama |
| putih | `ink` | 4.5 | Chip aktif, label jam stok |
| `primary-800` | putih | 4.5 | Tombol putih di atas foto hero |
| `primary-800` | `primary-50` | 4.5 | Label "Siap kirim hari ini" |
| `accent-700` | `accent-50` | 4.5 | Label "Belum dikonfirmasi" |
| `danger-700` | putih, `danger-50` | 4.5 | Pesan error, stok habis |
| `line-strong` | putih, `surface` | 3.0 | Tepi kolom isian (WCAG 1.4.11) |

## Tipografi

**Font:** Schibsted Grotesk, satu keluarga dengan bobot 400 sampai 800 (SIL OFL 1.1).
- **Web:** dimuat lewat `bunny()` di `vite.config.js` dan di-bundle ke `public/build`.
- **Android:** variable font di `design/android/res/font/schibsted_grotesk.ttf`. Lisensinya ada di `design/fonts/OFL.txt`.

| Peran | Ukuran / bobot | Spasi huruf |
| --- | --- | --- |
| Display (judul beranda) | 52 / 800 | -2 px |
| Judul (nama produk) | 32 / 800 | -1 px |
| Bagian | 22 / 800 | -0.5 px |
| Harga | 19 / 800 | -0.4 px |
| Isi | 16 / 400 | 0 |
| Keterangan | 13 / 500 | 0 |

Satuan harga ("/ kg") ditulis kecil dengan warna `muted` setelah angka harga.

## Bentuk dan jarak

- Radius (tersedia sebagai kelas Tailwind):
  - `rounded-photo` (16 px) untuk foto di dalam kartu;
  - `rounded-option` (18 px) untuk pilihan varian;
  - `rounded-card` (22 px) untuk kartu produk dan toko;
  - `rounded-feature` (28 px) untuk produk unggulan dan galeri besar;
  - `rounded-full` untuk tombol, chip, label, dan pencarian.
- Jarak memakai kelipatan 4: 4, 8, 12, 16, 24, 32, 48.
- Target sentuh minimal 44 px.
- Tanpa bayangan: kartu putih di atas pasir sudah cukup memisahkan.

## Komponen Blade

Komponen anonim di `backend/resources/views/components/`. Atribut tambahan seperti `class`, `wire:model`, dan `inputmode` diteruskan lewat `$attributes->merge()`. Semua teks dicetak dengan `{{ }}`.

| Komponen | Pakai untuk | Contoh |
| --- | --- | --- |
| `<x-button>` | Tombol pill: `primary` (hijau, satu per layar), `secondary` (garis `ink`), `ghost` (teks hijau). Dengan `href` menjadi tautan. `disabled` menonaktifkan; tautan nonaktif kehilangan `href` dan diberi `aria-disabled` | `<x-button href="/produk" variant="secondary">Lihat katalog</x-button>` |
| `<x-input>` | Kolom isian berlabel. `help` dan `error` dihubungkan lewat `aria-describedby`. Bila `error` tidak diisi, error validasi untuk `name` dipakai | `<x-input name="qty" label="Jumlah (kg)" help="Minimal 1 kg" inputmode="decimal" />` |
| `<x-select>` | Pilihan berlabel; opsi ada di slot | `<x-select name="kondisi" label="Kondisi">…</x-select>` |
| `<x-price>` | Harga rupiah `Rp185.000` dengan satuan opsional `/ kg`. Tidak ada `number_format` harga di view | `<x-price :amount="$variant->price" :unit="$variant->unit->label()" />` |
| `<x-badge>` | Label kecil: `neutral` (kondisi), `ink` (jam stok dicek), `success` (siap kirim), `warning` (stok belum dikonfirmasi), `danger` (stok habis) | `<x-badge variant="warning">Belum dikonfirmasi</x-badge>` |
| `<x-empty-state>` | Saat tidak ada isi, dengan aksi opsional (`action` dan `href`). `compact` untuk bingkai foto kosong | `<x-empty-state title="Belum ada produk" action="Hapus semua filter" href="/produk" />` |
| `<x-product-card>` | Kartu produk di beranda, katalog, dan halaman toko | `<x-product-card :product="$product" :distance="$product->distance_km" />` |
| `<x-logo>` | Tanda dan nama aplikasi di header | `<x-logo />` |

## Latar

- **Utama:** pasir polos (`surface`).
- **Cadangan:** pola laut di `backend/public/images/pola-laut.svg` (sumbernya `design/pattern/pola-laut.svg`). Pola ini berisi garis `pattern` yang diulang tiap 240 px.
  - Hanya untuk beranda dan katalog, tidak dipakai di detail produk atau checkout.
  - Kartu tetap putih polos di atasnya.

## Logo dan ikon

- **Tanda:** persegi putih bersudut membulat dengan garis `ink`, garis ikan di dalamnya, dan titik `accent-500` di pojok kanan atas.
  - Sumber: `design/logo/logo-mark.svg`. Versi gelap: `logo-mark-dark.svg`. Ikon aplikasi: `app-icon.svg`.
  - Komponen `<x-logo />` memakai tanda yang sama di samping `config('app.name')`. Tandanya disembunyikan dari pembaca layar, sedangkan nama aplikasi menjadi teks tautan.
- **Web** (`backend/public`):
  - `favicon.svg`;
  - `favicon.ico` (16, 32, 48 px);
  - `apple-touch-icon.png` (180 px);
  - `android-chrome-192.png` dan `-512.png`;
  - `site.webmanifest`.
- **Android** (`design/android/res`):
  - adaptive icon `mipmap-anydpi-v26/ic_launcher*.xml` dengan foreground vector, latar `primary-700`, dan layer monochrome untuk themed icon Android 13;
  - PNG lama di `mipmap-*dpi`;
  - `drawable/ic_logo_mark.xml` untuk dipakai di dalam aplikasi;
  - `playstore-icon.png` (512 px).
- PNG dan ICO dibuat ulang dengan `php design/scripts/generate-icons.php` dari bentuk yang sama dengan SVG. Jalankan ulang bila tanda atau warnanya berubah.
