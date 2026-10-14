# design/

Aset bersama web dan Android untuk sistem desain **Bakau dan Kepiting**. Aturan pemakaiannya ada di [`docs/ui-guidelines.md`](../docs/ui-guidelines.md).

| Path | Isi |
| --- | --- |
| `tokens.json` | Sumber kebenaran: warna, huruf, radius, jarak |
| `logo/` | `logo-mark.svg`, `logo-mark-dark.svg`, `app-icon.svg` |
| `pattern/pola-laut.svg` | Pola latar cadangan (ubin 240 px) |
| `fonts/OFL.txt` | Lisensi Schibsted Grotesk |
| `android/res/` | Siap disalin ke `android/app/src/main/res/` di Tahap 4: warna, logo vector, adaptive icon, PNG launcher, font |
| `android/theme/` | `Color.kt`, `Type.kt`, `Shape.kt`, `Theme.kt` untuk Jetpack Compose (Material 3). Ganti `package` dan `R` sesuai namespace aplikasi |
| `android/playstore-icon.png` | Ikon Play Store 512 px |
| `scripts/generate-icons.php` | Menggambar ulang PNG/ICO web dan Android dari bentuk logo |

Bila sebuah warna berubah:
1. Ubah `tokens.json`.
2. Ubah `backend/resources/css/app.css`, `docs/ui-guidelines.md`, `android/res/values/colors.xml`, dan `android/theme/Color.kt`.
3. Bila warna logo ikut berubah, jalankan `php design/scripts/generate-icons.php`.
4. Jalankan `composer check` di `backend/`. Test identitas visual akan memberi tahu kalau ada yang terlewat.
