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

```
backend/app/
  Domain/<Modul>/          # Catalog, Ordering, Inventory, Store, Shipping
    Actions/               # use case: PlaceOrder, ApprovePayment, RecordStockMovement
    Data/                  # DTO input/output (readonly class)
    Enums/                 # OrderStatus, ProductCondition, SalesMode, Unit
    Events/ Listeners/
    Exceptions/            # exception domain, mis. InsufficientStock
    Models/                # Eloquent model + relasi + scope, tanpa logika bisnis berat
    Policies/
  Http/
    Controllers/Api/V1/    # tipis: validasi -> Action -> Resource
    Requests/              # Form Request untuk validasi + authorize
    Resources/             # API Resource, format respons stabil
  Livewire/                # web pembeli, memanggil Action yang sama
  Filament/Admin/ Filament/Seller/
```

### Generator

File baru selalu dibuat dengan `php artisan make:*`, tidak ditulis manual dari nol. Path dengan `/` otomatis menjadi namespace di bawah `App\`.

| Kebutuhan | Perintah |
| --- | --- |
| Enum | `php artisan make:enum Domain/Ordering/Enums/OrderStatus --string` |
| Model + migration + factory | `php artisan make:model Domain/Catalog/Models/Product -mf` |
| Action, DTO, Exception | `php artisan make:class Domain/Ordering/Actions/PlaceOrder` (Laravel tidak punya `make:action`) |
| Policy | `php artisan make:policy Domain/Catalog/Policies/ProductPolicy --model=Domain/Catalog/Models/Product` |
| Form Request, API Resource | `php artisan make:request Api/V1/StoreProductRequest`, `php artisan make:resource Api/V1/ProductResource` |
| Controller API | `php artisan make:controller Api/V1/ProductController --api` |
| Livewire, Filament | `php artisan make:livewire ...`, `php artisan make:filament-resource ...` |
| Test | `php artisan make:test Domain/Ordering/OrderStatusTest --unit --pest` |

Template generator tidak dikustomisasi (`stub:publish` tidak dipakai). Konvensi seperti `declare(strict_types=1);` ditegakkan oleh Pint (rule `declare_strict_types` di `pint.json`, issue #6), sehingga file hasil generator langsung dirapikan saat commit.

### Aturan

- Controller dan komponen Livewire tidak boleh lebih dari: validasi, panggil satu Action, kembalikan respons.
- Setiap Action punya satu method publik `handle()` dan dibungkus `DB::transaction()` bila menulis lebih dari satu tabel.
- Perubahan stok **hanya** lewat `RecordStockMovement`; transisi status pesanan **hanya** lewat method di enum/state `OrderStatus::canTransitionTo()`.
- Query N+1 dicegah: `Model::preventLazyLoading()` aktif di non-production.
- Otorisasi lewat Policy; data toko selalu discoping ke toko milik user (store_members).
- API versi di path (`/api/v1`), respons error konsisten (format JSON yang sama untuk 4xx/5xx).

### Kualitas

- Format: **Laravel Pint** (preset laravel).
- Static analysis: **Larastan level 6**, naik bertahap.
- Test: **Pest**. Setiap Action punya unit/feature test; setiap endpoint API punya feature test untuk sukses, validasi gagal, dan akses ditolak.
- Database test memakai PostgreSQL (sama dengan production), bukan SQLite.
- **Test-first.** Untuk setiap issue, test ditulis lebih dulu sebagai spesifikasi (kasus valid, kasus yang dilarang, edge case), lalu implementasi ditulis sampai test hijau. Test dan boilerplate boleh dibuat dengan bantuan Claude Code; kode domain (Actions, aturan di Enums, Models) ditulis pemilik repo.

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

- Dependency injection dengan **Hilt**; tidak ada `new` repository di ViewModel.
- Coroutines + Flow; tidak ada callback bersarang. Dispatcher di-inject agar bisa dites.
- Error jaringan dipetakan ke tipe `Result`/sealed class domain, bukan exception mentah sampai ke UI.
- Semua teks lewat `strings.xml`.

### Kualitas

- Format dan lint: **ktlint** + **detekt**, plus Android Lint.
- Test: unit test untuk use case dan ViewModel (JUnit5, Turbine, MockK); UI test Compose untuk alur tambah produk.

## Git dan review

- Satu issue GitHub = satu branch = satu PR. Nama branch `jenis/<nomor-issue>-ringkas`, misalnya `feat/2-order-status`. Deskripsi PR memuat `Closes #<nomor>`.
- Commit mengikuti **Conventional Commits** (`feat(stock): add daily stock confirmation`).
- Satu PR = satu fitur kecil yang bisa direview; CI wajib hijau sebelum merge; merge dengan **Squash and merge** agar satu PR menjadi satu commit Conventional di `main`.
- CI (GitHub Actions): backend (Pint, Larastan, Pest dengan service PostgreSQL) dan android (ktlint, detekt, unit test, build APK debug).
- Rahasia tidak pernah masuk repository: `.env` dan `google-services.json` di `.gitignore`, contoh di `.env.example`.
