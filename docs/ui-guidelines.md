# Sistem desain: Bakau dan Kepiting

Identitas visual web pembeli dan aplikasi Android penjual. Dipilih lewat eksplorasi di [kanvas desain](https://claude.ai/artifact/7wff8QD6qsXZ3WUDtYefxp) (arah 2, palet 9c, latar pasir). Pola halaman dari penyegaran v0.3.1 ada di [Pola halaman](#pola-halaman-v031).

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
  - Oranye dipakai untuk titik logo, label stok, dan teks yang meminta perhatian soal waktu atau jumlah:
    - batas pesan ("Pesan sebelum 15.00 untuk dikirim besok");
    - kebutuhan resep ("Butuh 1 kg kepiting bakau");
    - label "Termurah" di perbandingan lapak.

    Oranye tidak pernah dipakai untuk tombol.
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

Sejak v0.3.1, judul halaman dan bagian memakai bobot 500 dengan spasi huruf rapat. Bobot 800 tetap untuk nama produk, harga, dan nama aplikasi. Ukuran di tabel ditulis ponsel / web, dalam px.

| Peran | Ukuran | Bobot | Spasi huruf | Contoh |
| --- | --- | --- | --- | --- |
| Display | 38 / 64 | 500 | -0.035 em | Judul foto hero beranda |
| Judul halaman | 32 / 44 | 500 | -0.025 em | "Semua pre order", nama toko, judul galat (36 / 56) |
| Nama produk | 32 / 40 | 800 | rapat | Halaman produk |
| Bagian | 22–26 / 28–34 | 500 | rapat | "Baru dikonfirmasi", "Lapak lain di sekitarnya" |
| Subbagian dan panel | 18–20 | 600 | 0 | "Saring", "Tentang lapak", judul langkah |
| Nama di kartu | 15 / 17 | 600 | 0 | Kartu produk, pre order, resep |
| Harga | 17 / 19 | 800 | rapat | `<x-price>` |
| Isi | 16 | 400 | 0 | Paragraf |
| Keterangan | 11–13 | 400–600 | 0 | Toko dan jam dicek di kartu, satuan |

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
- Tanpa bayangan: kartu putih di atas pasir sudah cukup memisahkan. Pengecualian:
  - ringkasan pesanan yang menempel di bawah layar ponsel memakai bayangan ke atas, supaya terpisah dari isi yang digulir di bawahnya;
  - pilihan aktif di grup segmen (misalnya jarak) memakai `shadow-sm`.

## Komponen Blade

Komponen anonim di `backend/resources/views/components/`. Atribut tambahan seperti `class`, `wire:model`, dan `inputmode` diteruskan lewat `$attributes->merge()`. Semua teks dicetak dengan `{{ }}`.

| Komponen | Pakai untuk | Contoh |
| --- | --- | --- |
| `<x-button>` | Tombol pill: `primary` (hijau, satu per layar), `secondary` (garis `ink`), `ghost` (teks hijau). Dengan `href` menjadi tautan. `disabled` menonaktifkan; tautan nonaktif kehilangan `href` dan diberi `aria-disabled` | `<x-button href="/produk" variant="secondary">Lihat katalog</x-button>` |
| `<x-input>` | Kolom isian berlabel. `help` dan `error` dihubungkan lewat `aria-describedby`. Bila `error` tidak diisi, error validasi untuk `name` dipakai | `<x-input name="qty" label="Jumlah (kg)" help="Minimal 1 kg" inputmode="decimal" />` |
| `<x-select>` | Pilihan berlabel; opsi ada di slot | `<x-select name="kondisi" label="Kondisi">…</x-select>` |
| `<x-price>` | Harga rupiah `Rp185.000` dengan satuan opsional `/ kg`. Tidak ada `number_format` harga di view | `<x-price :amount="$variant->price" :unit="$variant->unit->label()" />` |
| `<x-badge>` | Label kecil: `neutral` (kondisi), `ink` (jam stok dicek), `success` (siap kirim), `warning` (stok belum dikonfirmasi), `danger` (stok habis), `demo` (pil bergaris putus-putus "Toko contoh" untuk toko contoh) | `<x-badge variant="warning">Belum dikonfirmasi</x-badge>` |
| `<x-empty-state>` | Saat tidak ada isi, dengan aksi opsional (`action` dan `href`). `compact` untuk bingkai foto kosong | `<x-empty-state title="Belum ada produk" action="Hapus semua filter" href="/produk" />` |
| `<x-product-card>` | Kartu produk putih di beranda, katalog, dan halaman toko. `tone="surface"` memberi kartu pasir untuk bagian berlatar putih. `label` menaruh pil `ink` di atas foto ("Dicek 06.30"). Produk toko contoh otomatis berlabel "Toko contoh" | `<x-product-card :product="$product" :distance="$product->distance_km" />` |
| `<x-error-page>` | Isi halaman galat: kode, judul, penjelasan, tombol di slot, panel langkah bernomor (`steps-title`, `:steps`), kotak kategori, dan slot `after`. Lihat [Halaman galat](#halaman-galat) | `<x-error-page code="429" title="…" body="…" steps-title="Yang bisa Anda lakukan" :steps="[…]">…</x-error-page>` |
| `<x-error-category-tiles>` | Kotak foto kategori dari `config/error-pages.php`. `:counted="true"` menambah jumlah produk (dua query); tanpa itu tidak ada query | `<x-error-category-tiles :counted="true" />` |
| `<x-recently-checked-products>` | Empat produk yang stoknya paling baru dicek, dengan kartu "{toko}, dicek {jam}". Kosong (tidak dirender) bila database tidak bisa diakses | `<x-recently-checked-products />` |
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

## Pola halaman (v0.3.1)

Pola dari penyegaran UI v0.3.1, untuk dipakai ulang di halaman Tahap 5 (keranjang, checkout, pesanan, akun) dan di aplikasi Android. Setiap pola menyebut board acuannya di [kanvas desain](https://claude.ai/artifact/7wff8QD6qsXZ3WUDtYefxp); nama halaman kanvas ditulis dalam kurung.

### Kartu putih dan kartu pasir

- Kartu (produk, toko, resep, pre order) berwarna putih di atas latar pasir, tanpa garis tepi dan tanpa bayangan. Radius `rounded-card`, foto di dalamnya `rounded-photo`, padding 8–10 px dengan bawah sedikit lebih lega.
- Di atas pita putih (misalnya "Tidak mau menunggu?" di `/pre-order` atau "Dari {toko} juga" di halaman produk), kartu dibalik menjadi pasir: `<x-product-card tone="surface" />` atau kelas `bg-surface` untuk kartu khusus.
- Di ponsel, deretan kartu di dalam pita bergeser ke samping (`overflow-x-auto`, kartu 168–220 px); dari `lg` menjadi grid.
- Board: "Beranda Web A" dan "Katalog Web K2" (Eksperimen 1.1.0).

### Toko contoh

Aturan datanya ada di `docs/prd.md` (bagian "Toko contoh"). Di tampilan:

- Setiap tempat yang menyebut toko contoh memakai label `<x-badge variant="demo">Toko contoh</x-badge>`: kartu produk, kartu pre order, baris "Lapak lain", pilihan toko di saringan katalog (teks kecil di bawah nama), dan kartu toko.
- Selalu diurutkan setelah toko asli, di semua daftar (scope `realStoresFirst()` untuk produk, `orderBy('is_demo')` untuk toko). Label "Termurah" tidak pernah jatuh ke toko contoh.
- Tidak pernah tampil di "Lapak terverifikasi" di beranda.
- Halaman toko contoh tidak punya tombol WhatsApp, telepon, atau petunjuk arah. Gantinya catatan bergaris putus-putus (`role="note"`) yang menjelaskan bahwa toko ini contoh dan produknya tidak bisa dipesan. Halamannya `noindex`.
- Board: "Toko Web R1" (Eksperimen 1.3.0).

### Langkah bernomor dan ringkasan yang menempel

Untuk alur yang meminta beberapa keputusan berurutan, seperti halaman produk dan nanti checkout:

- Daftar `<ol>` dengan bulatan nomor `primary-700` (36 px) dan garis vertikal `primary-100` yang menghubungkan ke langkah berikutnya. Judul setiap langkah berupa pertanyaan pendek: "Pilih ukuran", "Berapa kilo?", "Kapan mau diterima?", "Diantar atau ambil sendiri?". Langkah yang tidak berlaku dilewati dan nomornya tetap berurutan.
- Ringkasan (total dan tombol utama) di bawah langkah:
  - ponsel: menempel di bawah layar (`sticky bottom-0`), putih dengan bayangan ke atas;
  - web: kartu di kolom kanan yang ikut saat digulir.
- Biaya yang belum bisa dihitung ditulis apa adanya ("Dihitung saat checkout"), bukan nol.
- Board: "Produk Web 4B" dan "Produk Ponsel 4B" (Eksperimen 1.2.0).

### Panel saring dan lembar dari bawah

Dipakai di `/produk` dan `/pre-order`:

- **Web (`lg`):** panel putih di kiri, lebar 280–288 px, radius `rounded-feature`, ikut saat digulir. Setiap kelompok berupa `<fieldset>` dengan `<legend>` tebal. Pilihan tunggal sebagai tombol `aria-pressed` atau radio dengan jumlah produk di kanan.
- **Ponsel:** bar yang menempel di atas berisi jumlah hasil dan tombol "Saring" (dengan angka saringan aktif). "Saring" membuka panel yang sama sebagai lembar dari bawah (`role="dialog"`, `aria-modal`). Escape, tombol tutup, atau latar gelap menutupnya, lalu fokus kembali ke "Saring". Bawah lembar berisi "Atur ulang" dan "Tampilkan {n} produk".
- Saringan yang paling sering dipakai boleh keluar dari lembar sebagai pil yang bisa digeser ke samping di bar atas, seperti "Dikirim paling lambat" di `/pre-order`.
- Jumlah di setiap pilihan dihitung dengan saringan lain tetap berlaku, tanpa saringan pilihan itu sendiri.
- Jarak hanya bisa dipilih setelah pembeli membagikan lokasi. Lokasi tidak masuk URL (DATA-4), dan teks di bawahnya menjelaskan bahwa lokasi tidak disimpan.
- Board: "Katalog Web K2" dan "Katalog Ponsel K2" (Eksperimen 1.1.0), "Pre Order Web 2" dan "Pre Order Ponsel 2" (Eksperimen 1.6.0).

### Tab bergaris bawah

- Deretan teks dengan garis bawah `line` di bawah seluruh deretan. Tab aktif tebal dengan garis `ink` 3 px, yang lain `muted`. Tanpa latar pil. Di ponsel deretan bergeser ke samping.
- Tab yang mengganti isi di halaman yang sama (Deskripsi, Standar kualitas, Rincian di halaman produk) memakai pola ARIA tab: `role="tablist"`, `role="tab"` dengan `aria-selected`, panah kiri dan kanan untuk pindah.
- Tab yang menyaring daftar (kategori di halaman toko) adalah tautan sungguhan dengan `aria-current="page"`, ditangani Livewire tanpa memuat ulang halaman, dengan jumlah produk di samping nama.
- Board: "Produk Web 4B" (Eksperimen 1.2.0) dan "Toko Web R1" (Eksperimen 1.3.0).

### Kartu resep dan titik penuntun bahan

- **Kartu resep:** foto 4:3, baris meta kecil (waktu dan porsi), nama, dan di bawah "Butuh {jumlah}" berwarna `accent-700`. Itu yang dibeli dari marketplace, jadi kartu menjawab "apa yang perlu saya beli".
- **Daftar bahan:** setiap baris berisi nama bahan, garis titik-titik `border-dotted line-strong` yang mengisi sisa lebar, lalu jumlahnya tebal. Bahan "secukupnya" tanpa garis dan tanpa jumlah. Pengatur porsi (1–12) mengubah semua jumlah di tempat.
- Bahan dan cara memasak tampil berdampingan di web, bahan di panel `primary-50`. Keduanya ikut tercetak, sedangkan bagian belanja disembunyikan saat mencetak.
- Board: "Resep Daftar 2", "Resep Detail 3C", dan versi ponselnya (Eksperimen 1.5.0).

### Kapasitas pre order

- **Sampai Tahap 5 (belum ada pesanan):** kartu pre order hanya menampilkan kapasitas dari `daily_quota`: "Kuota 20 kg per hari", dalam teks `primary-800`. Label foto "Paling cepat Sab 17" (pil `ink`) dihitung dari `Product::earliestDeliveryDate()`.
- **Setelah ada pesanan dengan `scheduled_date` (Tahap 5):** baris kuota diganti garis sisa kuota hari kirim paling cepat:
  - **cukup:** garis `primary-700`, teks "Sisa 12 dari 20 kg";
  - **hampir habis** (sisa 25% atau kurang): garis `accent-500`, teks `accent-700` "Hampir habis, sisa 4 dari 20 kg";
  - **penuh:** garis `line-strong`, teks "Penuh, pesan untuk {hari}", kartu diredupkan;
  - sakelar "Sembunyikan yang penuh" di panel saring.
- Board: "Pre Order Web 2" dan "Pre Order Ponsel 2" (Eksperimen 1.6.0).

### Halaman galat

- **Pola bersama:** "Kode {n}" kecil, judul besar (bobot 500), penjelasan dalam bahasa pembeli tanpa istilah teknis, dua tombol (muat ulang dengan `href=""` dan "Kembali ke beranda"), lalu kotak foto kategori. Komponennya `<x-error-page>`.
- **404:** kolom cari besar ke katalog di web (di ponsel cukup tautan "Kembali ke beranda", karena kolom cari sudah ada di header), kotak kategori dengan jumlah produk, dan "Baru dikonfirmasi pagi ini".
- **419, 429, 500:** panel `primary-50` berisi tiga langkah bernomor ("Cara melanjutkan", "Yang bisa Anda lakukan", "Sambil menunggu"), tanpa daftar produk.
- **Tanpa database di 419, 429, dan 500:** kotak kategori berasal dari daftar statis `config/error-pages.php`, karena 500 bisa terjadi saat database bermasalah dan 429 tidak boleh menambah beban. Query di 404 dibungkus supaya halaman tetap tampil tanpa bagian produk bila database mati.
- Tidak ada pesan exception, path, atau URL yang dicoba di halaman (DATA-5). Layout `bare` juga tidak mencetak canonical.
- Board: "Galat Web 1", "Galat Web 419", "Galat Web 429", "Galat Web 500", dan versi ponselnya (Eksperimen 1.4.0).

## Android: variasi Embun (Tahap 4)

Aplikasi penjual memakai variasi D "Embun" dari halaman kanvas "Eksperimen 2.0.0 variasi D: Embun (kaca)". Setiap layar punya board `VD-*` (misalnya `VD-Masuk`), dan setiap keadaan (galat, kosong, menunggu) punya board sendiri. Variasi E "Cap" disimpan sebagai cadangan. Komponennya ada di `core/designsystem/embun/` dan dipakai ulang oleh semua layar; warna dasar dan huruf tetap dari tema Bakau dan Kepiting (`core/designsystem/theme`).

- **Latar** (`EmbunBackground`): gradien vertikal hijau muda `#DDEBE2` → pasir `#F6F3EC` (42%) → peach `#F8E9DE`, di belakang seluruh layar.
- **Panel kaca tanpa blur** (`GlassCard`): putih 62% dengan garis putih 85% setebal 1 dp, radius 28, padding 18. Blur latar butuh API 31 dan dependency baru, jadi tidak dipakai (keputusan pemilik repo 18 Okt 2026). Panel yang lebih menonjol (chip, tombol sekunder) memakai putih 75%.
- **Isian kaca** (`GlassTextField`): label di atas (`labelMedium`, `#4F5A50`), kotak putih 78% dengan garis putih 95% setebal 1,5 dp, radius 18, tinggi minimal 52 dp. Saat galat, garisnya `danger-700` dan pesan galat menggantikan teks bantuan di bawahnya. Isian menyebut `contentType` untuk autofill (email, password). Isian beberapa baris (`minLines`, misalnya cerita toko dan alamat) mulai dari atas dengan jarak 14 dp di atas dan bawah teks.
- **Tombol pil utama** (`PrimaryPillButton`): `primary-700`, tinggi 54 dp, bayangan hijau lembut (`primary-700` 28%). Saat nonaktif menjadi kaca putih 55% dengan teks `muted`, tanpa bayangan.
- **Tombol pil kaca** (`GlassPillButton`): untuk aksi kedua, misalnya "Masuk dengan Google". Putih 85% dengan garis putih, tinggi 54 dp, teks `ink`, ikon di kiri teks. Logo Google tampil dengan warnanya sendiri, tidak diwarnai ulang.
- **Pemisah bertulisan** (`TextDivider`): garis `ink` 10% di kiri dan kanan kata pendek `muted`, misalnya "atau email" di antara tombol Google dan isian email.
- **Chip** (`GlassChip`): pil putih 75% dengan garis putih.
- **Pesan di panel** (`GlassBanner`): radius 18, peach untuk peringatan (misalnya 429) dan merah muda untuk galat, diumumkan pembaca layar (`liveRegion`). Boleh diakhiri satu tautan tebal (`BannerAction`), misalnya "Lihat status toko".
- **Bar atas formulir** (`EmbunTopBar`): tombol kembali bulat dari kaca (44 dp), lalu judul besar (34 sp, bobot 700) dan satu baris penjelasan `muted`. Layar yang tidak punya tujuan kembali (data toko setelah akun dibuat) tampil tanpa tombol.
- **Judul bagian bernomor** (`GlassSectionHeader`): angka dalam lingkaran putih 85% (32 dp), lalu judul 19 sp bobot 700, untuk formulir panjang yang dibagi beberapa panel ("1 Profil toko", "2 Lokasi lapak", "3 Rekening").
- **Peta lokasi toko** (`StoreFormFields`): peta MapLibre setinggi 260 dp dengan radius 18 dan latar polos `#ECE8DD` saat tile belum atau tidak termuat. Pin toko (bentuk yang sama dengan pin peta web) tetap di tengah dan petanya yang digeser. Tombol putih "Lokasi saya" di kiri bawah, atribusi "© OpenStreetMap" selalu terlihat di kanan bawah, dan koordinat pin ditulis cara Indonesia di bawah peta ("Titik: −6,1067000, 106,7740000").
- **Bar bawah kaca** (`GlassBottomBar`): putih 72% dengan sudut atas radius 26, menempel di bawah layar dan naik di atas keyboard. Isinya tombol utama formulir dan tautan kecil di bawahnya; isian di atasnya bergulir sendiri.
- **Langkah alur** (`StepIndicator`): satu garis 5 dp per langkah, `primary-700` sampai langkah sekarang dan putih 70% sesudahnya, dengan nama langkah bernomor di bawah garis ("1. Akun", "2. Data toko").
- **Layar status** (`StatusOrb`, `StatusHeading`, `StatusChip`): lingkaran kaca 160 dp berisi ikon dan satu kata, di dalam halo gradien 220 dp (hijau `#CFE3D7` saat menunggu, merah muda `#FCE9E6` saat ditolak atau ditangguhkan, pasir `#ECE6DA` saat tanpa koneksi). Di bawahnya judul 30 sp di tengah dan satu kalimat `muted`. Status di panel memakai pil kecil 12 sp dengan warna yang sama, dan teksnya `status.label` dari server agar sama dengan panel admin.
- **Tanpa koneksi** (`OfflineContent`): satu layar penuh dengan tanda "Tidak ada koneksi", kalimat "Anda tetap masuk.", dan tombol utama "Coba lagi". Dipakai saat aplikasi dibuka dan di layar status; penjual tidak dikeluarkan karena koneksi.
- **Tombol teks** (`TextPillButton`): aksi pelan seperti "Keluar", teks `primary-700` tanpa latar, area sentuh setinggi 44 dp.
- **Navigasi kapsul** (`MainShell`): kapsul kaca putih 75% setinggi 60 dp yang melayang 16 dp di atas bawah layar, dengan bayangan `ink` 12%. Tab terpilih menjadi pil `ink` dengan ikon dan nama berwarna putih, dan lebarnya 1,5 kali tab lain; tab lain hanya ikon, namanya dibacakan pembaca layar. Tab terpilih bertahan saat rotasi.
- **Daftar produk** (`ProductListScreen`): header nama toko kecil dan judul "Produk" 34 sp dengan tombol cari bulat kaca; saat dicari, header menjadi kolom pil kaca dengan tombol hapus. Di bawahnya chip saring (Semua, Aktif, Nonaktif, Diblokir) tanpa angka: chip terpilih `ink` dengan teks putih. Baris kaca radius 26: foto bulat 76 dp (logo bila belum ada foto), nama tebal, satu baris catatan (kategori, oranye untuk stok belum dikonfirmasi, merah untuk alasan blokir), harga "Mulai Rp185.000 /kg" (tanpa "Mulai" bila hanya satu varian aktif), lalu status dan jumlah varian di kanan. Produk nonaktif tampil 65%. Tombol tambah bulat hijau 60 dp melayang di atas kapsul.
- **Form produk** (`ProductFormScreen`, board `VD-Produk-Form` dan keadaannya): panel bernomor (jenis hasil laut, nama dan cerita, kondisi dan cara jual, ukuran dan harga), lalu panel "Tampilkan ke pembeli" dan tombol "Hapus produk" merah saat mengubah. Tombol simpan ada di bar kaca bawah, dan galat umum (tanpa koneksi, produk sudah dihapus) tampil di atasnya. Atribut kategori digambar sesuai tipenya: isian dengan satuan di kanan (`g`) untuk teks dan angka, pil untuk pilihan, dan pemilih tanggal untuk tanggal. Atribut wajib diberi bintang. Pilihan satu dari beberapa (kondisi, satuan, atribut pilihan) memakai pil `ChoicePill`: `ink` saat terpilih, kaca saat tidak. Ready stock dan pre order memakai dua segmen (`SegmentedChoice`) dengan segmen terpilih putih. Saat simpan ditolak, layar bergulir ke isian galat pertama sesuai urutan form.
- **Sheet** (`EmbunSheet`): panel kaca putih 94% radius 32 di atas yang naik dari bawah, di atas layar yang diredupkan. Dipakai untuk pemilih kategori (`VD-Pilih-Kategori`: kategori induk membuka daftar pil anaknya, hanya kategori paling spesifik yang bisa dipilih), konfirmasi hapus, dan "Buang perubahan?". Aksi merusak memakai pil merah penuh, dan batal memakai pil kaca di bawahnya. Sheet digambar di dalam layar, bukan jendela terpisah, sehingga tombol Kembali menutupnya lebih dulu.
- **Stok hari ini** (`StockTodayScreen`, board `VD-Stok-Hari-Ini` dan keadaannya): tanggal hari ini di atas judul, lalu panel cincin "N/M" berisi ukuran yang sudah dicek hari ini. Setiap produk menjadi panel kaca dengan satu baris per ukuran: nama, kapan terakhir dicek ("Dicek 05.40", "Dicek kemarin 06.15", "Belum dicek 3 hari", atau "Belum pernah dicek" dengan label oranye "Belum dicek"), lalu tombol bulat − dan + 50 dp yang mengapit isian angka. Langkahnya 0,5 untuk kg dan 1 untuk satuan lain, dan − nonaktif di 0. Isian yang diubah berwarna hijau muda dengan catatan oranye "Diubah dari ..." dan tautan "Kembalikan"; isian yang salah diberi garis merah dan galat di bawahnya. Satu tombol hijau di atas kapsul: "Sama seperti kemarin" tanpa perubahan, "Simpan N perubahan" dengan perubahan, dan nonaktif selama ada isian salah. Galat simpan tampil di atas tombol, dan perubahan tetap ada.
- **Buku stok** (`StockBookScreen`, board `VD-Buku-Stok` dan `VD-Catat-Stok` beserta keadaannya): dibuka dari tautan "Buka buku stok" di Stok hari ini, dengan tombol Kembali bulat. Tiga chip saringan (tanggal, jenis, ukuran) membuka satu lembar saringan; chip yang sedang menyaring berwarna `ink`. Catatan dikelompokkan per hari ("Hari ini", "Kemarin", lalu tanggalnya) dalam panel kaca: tanda + atau − 40 dp, jenis dari server, produk dan ukuran, detail (penyetor, harga beli per satuan, catatan, pencatat, jam), serta jumlah bertanda dan "jadi ..." di kanan. Susut berwarna oranye. Tombol hijau "Catat perubahan" (radius 14) membuka lembar catat dengan empat ubin jenis 2×2; penyetor dan harga beli hanya muncul untuk stok masuk, dan hanya penyesuaian yang boleh minus.
- **Angka**: semua layar memakai `IndonesianNumbers`: `Rp45.000`, dan berat atau jumlah dari `decimal(10,3)` tanpa nol di belakang (`1,5`, `2`, `0,25`).
- **Foto bulat**: foto produk dipotong bulat dengan bayangan gelap lembut, dipakai sebagai hiasan (tanpa `contentDescription`). Foto di layar masuk berasal dari foto demo berlisensi CC0, jadi tidak perlu kredit.
- **Teks**: semua dari `strings.xml`. Pesan galat server untuk login memakai teks aplikasi sendiri, bukan pesan server (AUTH-3).
