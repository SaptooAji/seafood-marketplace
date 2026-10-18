# Standar Engineering: Marketplace Hasil Laut

Dokumen ini adalah aturan kerja untuk semua kode di repository: backend Laravel dan aplikasi Android Kotlin. Setiap PR dicek terhadap aturan ini.

## Prinsip umum

- **Satu tempat untuk satu aturan bisnis.** Logika (potong stok, pisah pesanan per toko, transisi status) hanya ada di lapisan domain/use case, tidak di controller, komponen UI, atau view.
- **Dependency mengarah ke dalam.** UI dan framework bergantung pada domain, bukan sebaliknya.
- **Nama menjelaskan maksud.** Bahasa Inggris untuk kode (kelas, method, kolom), Bahasa Indonesia untuk teks yang dilihat pengguna (lewat file terjemahan).
- **Fungsi kecil, satu tanggung jawab.** Kalau butuh komentar untuk menjelaskan *apa*, pecah fungsinya; komentar hanya untuk *mengapa*.
- **Tidak ada magic value.** Status, kondisi, satuan, dan role memakai enum; angka konfigurasi (24 jam stok basi, batas bayar) dari `settings`.
- **Uang dan berat tidak memakai float.** Harga disimpan dalam rupiah sebagai integer; berat dan qty memakai `decimal(10,3)`.

## Backend (Laravel 13, PHP 8.4)

### Struktur

Struktur mengikuti direktori bawaan Laravel, sehingga generator, tebakan factory, dan auto-discovery Policy bekerja tanpa konfigurasi tambahan. Tidak ada folder dasar khusus seperti `app/Domain`.

```
backend/app/
  Actions/                 # use case: PlaceOrder, ApprovePayment, RecordStockMovement
  Data/                    # DTO input/output (readonly class)
  Enums/                   # OrderStatus, ProductCondition, SalesMode, Unit
  Events/ Listeners/
  Exceptions/              # exception domain, mis. InsufficientStock
  Models/                  # Eloquent model + relasi + scope, tanpa logika bisnis berat
  Policies/                # ditemukan otomatis untuk model di App\Models
  Http/
    Controllers/Api/V1/    # tipis: validasi -> Action -> Resource
    Requests/              # Form Request untuk validasi + authorize
    Resources/             # API Resource, format respons stabil
  Livewire/                # web pembeli, memanggil Action yang sama
  Filament/Admin/ Filament/Seller/
backend/database/factories/   # factory per model, tanpa properti $model
backend/tests/Unit/<Area>/ tests/Feature/<Area>/   # area: Account, Catalog, Inventory, Ordering, Shipping, Store
```

### Generator

File baru selalu dibuat dengan `php artisan make:*`, tidak ditulis manual dari nol. Path dengan `/` otomatis menjadi namespace di bawah `App\`.

| Kebutuhan | Perintah |
| --- | --- |
| Enum | `php artisan make:enum OrderStatus --string` (masuk ke `app/Enums`) |
| Model + migration + factory | `php artisan make:model Product -mf` |
| Action, DTO | `php artisan make:class Actions/PlaceOrder`, `php artisan make:class Data/OrderData` (Laravel tidak punya `make:action`) |
| Exception | `php artisan make:exception InsufficientStock` |
| Policy | `php artisan make:policy ProductPolicy --model=Product` |
| Event, Listener | `php artisan make:event OrderPlaced`, `php artisan make:listener SendOrderNotification --event=OrderPlaced` |
| Form Request, API Resource | `php artisan make:request Api/V1/StoreProductRequest`, `php artisan make:resource Api/V1/ProductResource` |
| Controller API | `php artisan make:controller Api/V1/ProductController --api` |
| Livewire, Filament | `php artisan make:livewire ...`, `php artisan make:filament-resource ...` |
| Test | `php artisan make:test Ordering/OrderStatusTest --unit --pest`, `php artisan make:test Store/CreateStoreTest --pest` |

Template generator tidak dikustomisasi (`stub:publish` tidak dipakai). Konvensi seperti `declare(strict_types=1);` ditegakkan oleh Pint (rule `declare_strict_types` di `pint.json`, issue #6), sehingga file hasil generator langsung dirapikan saat commit.

### Aturan

- Controller dan komponen Livewire tidak boleh lebih dari: validasi, panggil satu Action, kembalikan respons.
- Setiap Action punya satu method publik `handle()` dan dibungkus `DB::transaction()` bila menulis lebih dari satu tabel.
- Perubahan stok **hanya** lewat `RecordStockMovement`; transisi status pesanan **hanya** lewat method di enum/state `OrderStatus::canTransitionTo()`.
- Query N+1 dicegah: `Model::preventLazyLoading()` aktif di non-production.
- Otorisasi lewat Policy; data toko selalu discoping ke toko milik user (store_members).
- API versi di path (`/api/v1`, diatur lewat `apiPrefix` di `bootstrap/app.php`), respons error konsisten: request ke `api/*` selalu dijawab JSON walau tanpa header `Accept`, dengan bentuk `{"message": "..."}` ditambah `"errors": {"field": ["..."]}` untuk 422. Tamu di route `auth:sanctum` mendapat 401, bukan redirect. Saat `APP_DEBUG=false`, error 500 hanya berisi `{"message": "Server Error"}` tanpa detail exception.

### Package

Package yang disetujui pemilik repo. Menambah atau mengubah dependency composer selalu butuh persetujuan lebih dulu.

| Package | Fungsi | Tahap |
| --- | --- | --- |
| `laravel/sanctum ^4` | Token API Android | 2 |
| `laravel/socialite ^5` | Login Google di web | 2 |
| `filament/filament ^5` | Panel `/admin` dan `/seller` | 2 |
| `spatie/laravel-permission ^8`, `bezhansalleh/filament-shield ^4` | Role platform dan permission panel | 2 |
| `spatie/laravel-activitylog ^5` | Jejak audit | 2 |
| `livewire/livewire ^4` | Web pembeli (sebelumnya hanya terpasang lewat Filament) | 3 |
| `intervention/image ^4` | Resize foto produk (driver GD; extension `gd` wajib di server dan CI) | 3 |

### Kualitas

- Format: **Laravel Pint** (preset laravel).
- Static analysis: **Larastan level 6**, naik bertahap.
- Test: **Pest**. Setiap Action punya unit/feature test; setiap endpoint API punya feature test untuk sukses, validasi gagal, dan akses ditolak.
- Database test memakai PostgreSQL (sama dengan production), bukan SQLite.
- Suite lengkap (`composer test`, `composer check`, hook pre-push, CI) berjalan paralel dengan `php artisan test --parallel`: satu proses per core, masing-masing dengan database sendiri (`seafood_marketplace_test_test_1`, `_test_2`, ...) yang dibuat Laravel dari `DB_DATABASE` di `phpunit.xml` dan dimigrasi ulang oleh `RefreshDatabase` di awal setiap run. User database test butuh izin `CREATEDB`. Test tidak boleh bergantung pada urutan baris tanpa `ORDER BY`, urutan file test, atau state bersama di luar database (file di disk tanpa `Storage::fake`). Menjalankan satu file atau `--filter` cukup dengan `php artisan test`, tanpa paralel.
- **Test-first.** Untuk setiap issue, test ditulis lebih dulu sebagai spesifikasi (kasus valid, kasus yang dilarang, edge case), lalu implementasi ditulis sampai test hijau. Test dan boilerplate boleh dibuat dengan bantuan Claude Code; kode domain (Actions, aturan di Enums, Models) ditulis pemilik repo. Untuk Android, Claude Code menulis test dan implementasi, lalu pemilik repo mereview dan meng-commit implementasinya (lihat CLAUDE.md "Pembagian peran").

## Android (Kotlin, Jetpack Compose)

### Struktur (per fitur)

```
android/app/src/main/java/.../
  core/        # network (Retrofit, interceptor auth), database (Room), designsystem, util
  feature/
    product/   # data/ domain/ ui/
    stock/
    order/
    auth/
```

- `domain`: model murni Kotlin dan use case, tanpa dependency Android.
- `data`: repository implementasi, DTO, mapper DTO -> domain, Retrofit service, Room DAO.
- `ui`: Composable tanpa logika; `ViewModel` mengekspos satu `StateFlow<UiState>` dan menerima event (unidirectional data flow).

### Aturan

- Proyek di `android/`, satu modul `app`, `applicationId` `io.github.saptooaji.seafood.seller`, `minSdk` 26, `targetSdk` terbaru yang stabil, JDK 17. Versi dependency dikelola di `gradle/libs.versions.toml`.
- Dependency injection dengan **Hilt**; tidak ada `new` repository di ViewModel.
- Coroutines + Flow; tidak ada callback bersarang. Dispatcher di-inject agar bisa dites.
- Error jaringan dipetakan ke tipe `Result`/sealed class domain, bukan exception mentah sampai ke UI.
- Semua teks lewat `strings.xml`.
- Angka desimal (berat, qty, stok) memakai `BigDecimal` dari string API, tidak `Double`; uang `Long` rupiah.
- Tampilan mengikuti tema di `design/android/theme/` dan `docs/ui-guidelines.md`, tanpa dynamic color. Setiap layar baru punya board `Android: ...` di kanvas desain yang disetujui sebelum test ditulis.

### Dependency

Dependency yang disetujui pemilik repo (Tahap 4). Menambah dependency di luar daftar ini butuh persetujuan lebih dulu.

| Kelompok | Dependency |
| --- | --- |
| UI dan navigasi | Compose BOM, Navigation Compose |
| DI dan jaringan | Hilt, Retrofit, OkHttp (termasuk `mockwebserver` untuk test), kotlinx.serialization, kotlinx.coroutines |
| Gambar dan foto | Coil, cropper foto (uCrop atau cropper Compose, dipilih di issue foto) |
| Penyimpanan token | DataStore, Tink |
| Login Google | Credential Manager, `googleid` |
| Peta | MapLibre Android |
| Test | JUnit5, MockK, Turbine, Compose UI test, Robolectric |
| Lint | ktlint (plugin Gradle), detekt |

### Kualitas

- Format dan lint: **ktlint** + **detekt**, plus Android Lint.
- Test: unit test untuk use case dan ViewModel (JUnit5, Turbine, MockK); UI test Compose untuk alur tambah produk, dijalankan di JVM dengan Robolectric.
- `./gradlew check` (dari `android/`) setara `composer check`: lint, detekt, Android Lint, dan semua test, termasuk UI test Robolectric, sehingga ikut hook pre-push dan CI tanpa emulator.

## Git dan review

- Milestone per tahap PRD (`Tahap N: <nama>`); setiap milestone yang selesai ditandai tag beranotasi dan GitHub Release (`v0.N.0`, Tahap 7 = `v1.0.0`, perbaikan di antara tahap memakai patch `v0.N.1`). Catatan rilis disimpan di `docs/releases/vX.Y.Z.md` dan menjadi deskripsi GitHub Release; tag dibuat setelah PR catatan rilis di-merge. Langkah lengkap ada di CLAUDE.md "Menutup milestone".
- Satu issue GitHub = satu branch = satu PR. Nama branch `<jenis>/<nomor-issue>-<ringkas>`: jenis sama dengan label issue (`feat`, `fix`, `chore`, `docs`, `test`, `refactor`), ringkas 2–4 kata bahasa Inggris dalam kebab-case, misalnya `feat/2-order-status`. Deskripsi PR memuat `Closes #<nomor>`.
- Branch selalu dibuat dari `main` terbaru; tidak ada commit langsung ke `main`. Sinkronkan dengan `git merge main`, bukan rebase, setelah branch di-push. Branch dihapus setelah di-merge.
- Commit mengikuti **Conventional Commits** (`feat(stock): add daily stock confirmation`).
- Satu PR = satu fitur kecil yang bisa direview; CI wajib hijau sebelum merge; merge dengan **Squash and merge** agar satu PR menjadi satu commit Conventional di `main`.
- CI (GitHub Actions): backend (`composer audit`, `npm audit`, Pint, Larastan, Pest paralel dengan service PostgreSQL) dan android (ktlint, detekt, unit test, build APK debug). Audit hanya di CI, tidak di hook pre-push. Token workflow hanya `contents: read`.
- Dependabot (`.github/dependabot.yml`) membuka PR mingguan `chore(deps): ...` untuk composer, npm, dan GitHub Actions; update minor dan patch digabung dalam satu PR per ekosistem. PR ini direview dan di-merge seperti PR lain setelah CI hijau.
- Semua job ada di satu workflow `.github/workflows/ci.yml`. Job `changes` mendeteksi area yang berubah (`backend/`, `android/`); job area hanya jalan bila areanya berubah, dan perubahan pada `ci.yml` atau `.githooks/` menjalankan semuanya. Aturan area ada di `.githooks/_areas.sh` (dites oleh `.githooks/tests/areas.sh` di job `changes`) dan dipakai juga oleh hook pre-push, sehingga hook memeriksa area yang sama dengan CI: push dokumen saja tidak menjalankan pemeriksaan, push backend tetap menjalankan `composer check` lengkap termasuk Pest. Bila perbandingan dengan remote gagal, hook menjalankan semua pemeriksaan. Job `CI` adalah satu-satunya status check wajib di ruleset `main`; job yang dilewati dihitung lolos. Job baru cukup ditambahkan ke `needs` job `CI`, tanpa mengubah ruleset.
- Rahasia tidak pernah masuk repository: `.env` dan `google-services.json` di `.gitignore`, contoh di `.env.example`.

## Keamanan

Aturan keamanan ada di [security-standards.md](security-standards.md), mengacu ke OWASP ASVS level 2, OWASP API Security Top 10, OWASP Top 10 for LLM, dan MASVS dasar. Setiap issue mengisi bagian **Keamanan** dan setiap PR direview terhadap checklist-nya. Celah dilaporkan lewat [SECURITY.md](../SECURITY.md), bukan issue publik.
