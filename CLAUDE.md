# CLAUDE.md

Panduan untuk Claude Code di repository ini. Balas dalam Bahasa Indonesia; kode, nama kelas, dan pesan commit dalam bahasa Inggris.

## Proyek

Marketplace hasil laut multi-penjual (rebuild skripsi, dipakai sebagai portofolio). Monorepo:

- `backend/`: Laravel 13, PHP 8.4, PostgreSQL 16. Web pembeli (Blade + Livewire), panel Filament `/admin` dan `/seller`, REST API Sanctum `/api/v1`.
- `android/` (belum dibuat): aplikasi penjual, Kotlin + Jetpack Compose.
- `docs/`: dokumen acuan. Baca sebelum mengerjakan fitur:
  - [docs/prd.md](docs/prd.md): scope MVP, model data (ERD), alur status pesanan, daftar endpoint API.
  - [docs/engineering-standards.md](docs/engineering-standards.md): struktur kode, aturan, tooling, alur git.

Pekerjaan dilacak sebagai GitHub issue per milestone (Tahap 1, 2, ...). Kriteria selesai ada di issue; baca issue-nya dulu (`gh issue view <nomor>`).

`backend/CLAUDE.md` dibuat otomatis oleh Laravel Boost (`boost:update`). Jangan edit file itu; aturan proyek ditulis di file ini.

## Pembagian peran

- **Pemilik repo** menulis kode domain: logika di Actions, aturan di Enums (misalnya transisi status), Models, Policies. Ini inti portofolio.
- **Claude Code** menulis test, boilerplate, konfigurasi, migrasi berdasarkan ERD bila diminta, dan dokumentasi.
- Jangan menulis atau melengkapi implementasi domain kecuali diminta secara eksplisit. Kalau test gagal karena implementasi belum ada, itu memang tahap yang diharapkan; laporkan test mana yang merah dan kenapa.
- Saat diminta review, jelaskan masalah dan alasannya; biarkan pemilik repo yang memperbaiki kecuali diminta.

## Alur kerja per issue (test-first)

1. Branch dari `main`: `jenis/<nomor-issue>-ringkas`, misalnya `feat/2-order-status`.
2. Tulis test lebih dulu dari kriteria di issue dan aturan di PRD: kasus valid, kasus yang dilarang, dan edge case. Pakai Pest dataset untuk kasus yang berulang, dengan key deskriptif (`'payment review to scheduled' => [...]`).
3. Pemilik repo menulis implementasi sampai test hijau.
4. `composer check` harus hijau sebelum push.
5. Commit memakai Conventional Commits. Commit test: `test(<modul>): ...`. Biarkan trailer `Co-Authored-By` pada commit yang dibuat Claude Code.
6. **Jangan push dan jangan membuka PR.** Pemilik repo yang push dan membuka PR dengan `Closes #<nomor>`; merge memakai squash.

## Perintah (jalankan dari `backend/`)

| Perintah | Fungsi |
| --- | --- |
| `composer check` | Pint (cek), Larastan, Pest. Sama dengan CI dan hook pre-push |
| `composer fix` | Pint (format), Larastan, Pest |
| `composer format` | Format dengan Pint |
| `composer analyse` | Larastan level 6 |
| `php artisan test --filter=OrderStatus` | Jalankan test tertentu |

Hook git ada di `.githooks/` (aktif lewat `core.hooksPath`): pre-commit memformat file PHP yang di-stage, pre-push menjalankan `composer check`. Lingkungan pemilik repo adalah Windows 11 dengan Laravel Herd dan Git Bash; di Git Bash, `vendor/bin/*` tidak bisa dijalankan langsung, jadi pakai `composer exec <tool>` atau script composer.

## Konvensi backend

- **Struktur domain** `app/Domain/<Modul>/{Actions,Data,Enums,Events,Listeners,Exceptions,Models,Policies}` sudah disetujui, walau Boost menyarankan tidak membuat folder dasar baru. Modul: Catalog, Ordering, Inventory, Store, Shipping.
- **Generator dulu.** Buat file dengan `php artisan make:*` (lihat tabel di engineering-standards). Action, DTO, dan Exception dengan `make:class`.
- **Strict types** di semua file PHP (ditegakkan Pint, rule `declare_strict_types`).
- **Enum**: backed enum string; nama case PascalCase (`AwaitingPayment`), nilai snake_case (`'awaiting_payment'`); setiap enum punya `label(): string` berbahasa Indonesia. Ejaan `cancelled` dengan double L.
- **Array bertipe** di docblock (`/** @return list<self> */`) karena Larastan level 6.
- Controller dan komponen Livewire tipis: validasi, panggil satu Action, kembalikan respons. Action punya satu method publik `handle()`, dibungkus `DB::transaction()` bila menulis lebih dari satu tabel.
- Stok hanya berubah lewat `RecordStockMovement`. Status pesanan hanya berubah setelah dicek `OrderStatus::canTransitionTo()`. Enum hanya menjawab transisi mana yang mungkin; siapa yang boleh dan kapan diatur di Action atau Policy.
- Uang dalam rupiah sebagai integer; berat dan qty `decimal(10,3)`. Tidak ada float.
- Test database memakai PostgreSQL, bukan SQLite. Unit test untuk enum dan logika murni di `tests/Unit/Domain/<Modul>/`; feature test untuk Action dan endpoint.
- Tidak ada `dd`, `dump`, `ray`, `var_dump` yang ter-commit (dicek arch test).
- Jangan menambah atau mengubah dependency composer tanpa persetujuan.
