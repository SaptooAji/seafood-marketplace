# CLAUDE.md

Panduan untuk Claude Code di repository ini. Balas dalam Bahasa Indonesia; kode, nama kelas, dan pesan commit dalam bahasa Inggris.

## Proyek

Marketplace hasil laut multi-penjual (rebuild skripsi, dipakai sebagai portofolio). Monorepo:

- `backend/`: Laravel 13, PHP 8.4, PostgreSQL 16. Web pembeli (Blade + Livewire), panel Filament `/admin` dan `/seller`, REST API Sanctum `/api/v1`.
- `android/` (belum dibuat): aplikasi penjual, Kotlin + Jetpack Compose.
- `docs/`: dokumen acuan. Baca sebelum mengerjakan fitur:
  - [docs/prd.md](docs/prd.md): scope MVP, model data (ERD), alur status pesanan, daftar endpoint API.
  - [docs/engineering-standards.md](docs/engineering-standards.md): struktur kode, aturan, tooling, alur git.
  - [docs/security-standards.md](docs/security-standards.md): aturan keamanan berkode (`AC-2`, `VAL-4`, ...), checklist issue dan review, klasifikasi temuan.

Pekerjaan dilacak sebagai GitHub issue per milestone (Tahap 1, 2, ...). Kriteria selesai ada di issue; baca issue-nya dulu (`gh issue view <nomor>`).

`backend/CLAUDE.md` dibuat otomatis oleh Laravel Boost (`boost:update`). Jangan edit file itu; aturan proyek ditulis di file ini.

## Pembagian peran

- **Pemilik repo** menulis kode domain: logika di Actions, aturan di Enums (misalnya transisi status), Models, Policies. Ini inti portofolio.
- **Claude Code** menulis test, boilerplate, konfigurasi, migrasi berdasarkan ERD bila diminta, dan dokumentasi.
- Jangan menulis atau melengkapi implementasi domain kecuali diminta secara eksplisit. Kalau test gagal karena implementasi belum ada, itu memang tahap yang diharapkan; laporkan test mana yang merah dan kenapa.
- **Android (`android/`)**: Claude Code menulis test dan implementasi. Test di-commit sebagai `test(android): ...`; implementasi dibiarkan belum di-commit sampai `./gradlew check` hijau, lalu pemilik repo mereview, meng-commit, dan push.
- Saat diminta review, jelaskan masalah dan alasannya; biarkan pemilik repo yang memperbaiki kecuali diminta.
- Review PR selalu mencakup checklist keamanan di `docs/security-standards.md`. Temuan ditulis dengan kode aturan dan tingkatnya (kritis, tinggi, sedang, rendah). Detail celah yang belum diperbaiki tidak ditulis di issue atau komentar publik.

## Merencanakan pekerjaan: milestone

Roadmap dilacak sebagai GitHub milestone, satu per tahap di "Tahapan pengerjaan" pada `docs/prd.md`.

- **Nama**: `Tahap N: <nama>`, misalnya `Tahap 2: Auth dan toko`. **Deskripsi**: tujuan tahap, kriteria selesai, dan versi rilisnya.
- **Semua milestone dibuat di awal** agar roadmap terlihat. Kalau PRD menambah atau mengubah tahap, perbarui milestone-nya juga.
- **Issue hanya ditulis untuk tahap yang sedang berjalan.** Issue tahap berikutnya ditulis saat tahap sekarang hampir selesai, karena detailnya bergantung pada hasil tahap sebelumnya.
- **Menutup milestone**: setelah semua issue-nya tertutup dan CI di `main` hijau, rilis dengan versi di deskripsi milestone (Tahap 1 = `v0.1.0`, Tahap 2 = `v0.2.0`, ..., Tahap 7 = `v1.0.0`; perbaikan di antara tahap memakai patch, misalnya `v0.1.1`). Alurnya:
  1. **Claude Code** membuat issue `docs(release): ...` di milestone itu, lalu menulis `docs/releases/vX.Y.Z.md` di branch `docs/<nomor>-release-notes`. Isinya: baris judul tebal (versi, tahap, tanggal), ringkasan, sorotan, daftar PR per kelompok (fitur, test dan kualitas, CI dan alur kerja, dokumentasi) dengan judul Conventional Commits dan nomor PR/issue, angka (issue, PR, test), dan tahap berikutnya. Data diambil dari `gh pr list`, `gh issue list --milestone`, dan hasil test, bukan ditebak.
  2. **Pemilik repo** push dan merge PR catatan rilis itu.
  3. **Claude Code** membuat tag beranotasi di `main` terbaru (yang sudah berisi file catatan): `git switch main && git pull && git tag -a vX.Y.Z -m "vX.Y.Z: Tahap N, <nama>"`. Tag tidak di-push.
  4. **Pemilik repo** menjalankan `git push origin vX.Y.Z`, lalu membuat GitHub Release lewat UI: pilih tag itu, judul `vX.Y.Z: Tahap N, <nama>`, deskripsi berisi seluruh isi `docs/releases/vX.Y.Z.md`. Setelah itu menutup milestone.

## Papan kerja: GitHub Project

Issue dilacak di project [Seafood Marketplace Roadmap](https://github.com/users/SaptooAji/projects/2) (nomor `2`, owner `SaptooAji`). Field **Status**: Todo, In Progress, Done.

- **Todo**: issue baru masuk otomatis (workflow *Auto-add*). Setelah membuat issue, pastikan tetap ada di project dengan `gh project item-add 2 --owner SaptooAji --url <url-issue>` (aman diulang).
- **In Progress**: diatur oleh hook `post-checkout` saat branch issue dibuat (`git switch -c <jenis>/<nomor>-...`). Kalau hook mencetak peringatan atau dilewati (`SKIP_PROJECT_HOOK=1`), ubah statusnya dengan `gh project item-edit`.
- **Done**: otomatis saat issue ditutup lewat PR `Closes #<nomor>`.
- `gh` butuh scope `project` (`gh auth refresh -s project`).

## Merencanakan pekerjaan: issue

Setiap pekerjaan dimulai dari GitHub issue di milestone tahapnya. Saat diminta merencanakan fitur atau tahap:

1. Pecah fitur menjadi beberapa issue (aturan di bawah). Untuk fitur dengan lebih dari dua issue, buat satu issue induk dan jadikan sisanya sub-issue.
2. Tulis setiap issue dengan template `.github/ISSUE_TEMPLATE/task.md`. Bagian wajib:
   - **Tujuan** dan **Kriteria selesai** berupa checklist.
   - **Test case**: daftar kasus valid, kasus yang ditolak, dan edge case, cukup detail sehingga test bisa ditulis tanpa menebak. Ambil aturannya dari PRD.
   - **Cara mulai**: perintah branch dan `php artisan make:*`.
   - **Keamanan**: jawaban checklist di `docs/security-standards.md` dengan rujukan kode aturan, dan kasus keamanan di **Test case** (minimal akses data toko lain atau peran yang tidak berhak, serta input berbahaya bila ada teks atau file).
   - **Petunjuk**: pola kode dan rujukan. Kalau perlu contoh, pakai domain lain; jangan tulis solusi lengkap.
   - **Di luar scope**.
3. Beri label jenis (`feat`, `fix`, `chore`, `docs`, `test`, `refactor`), area (`backend`, `android`, `ci`), dan ukuran (`size: S`, `size: M`), lalu pasang milestone dan masukkan ke project (lihat "Papan kerja").
4. Issue ditulis dalam Bahasa Indonesia, judul dalam format Conventional Commits.

### Ukuran issue

Utamakan issue kecil yang bisa dipelajari dan direview sekali duduk:

- Satu issue = satu PR = satu lapisan yang bisa dites sendiri. Contoh untuk fitur checkout: (1) migrasi dan model `orders` beserta factory, (2) Action `PlaceOrder` dengan feature test, (3) endpoint atau komponen Livewire yang memanggil Action itu, (4) notifikasi.
- Target `size: S` (setengah hari) atau `size: M` (sekitar satu hari). `size: L` berarti issue harus dipecah lagi.
- Setiap issue harus meninggalkan `main` dalam keadaan hijau dan bisa di-merge sendiri, walau fiturnya belum lengkap.
- Gabungkan dalam satu issue hanya kalau memecahnya membuat salah satu bagian tidak bisa dites, misalnya enum dan aturan transisinya.

## Aturan branch

- Satu issue = satu branch = satu PR. Format: `<jenis>/<nomor-issue>-<ringkas>`.
  - `<jenis>` sama dengan label issue: `feat`, `fix`, `chore`, `docs`, `test`, `refactor`.
  - `<ringkas>`: 2–4 kata bahasa Inggris, huruf kecil, dipisah tanda hubung. Contoh: `feat/2-order-status`, `fix/15-stock-rounding`, `chore/6-strict-types`.
- Selalu buat dari `main` terbaru: `git switch main && git pull && git switch -c feat/2-order-status`.
- Tidak ada commit langsung ke `main`; semua perubahan lewat PR, termasuk dokumen. Kalau sedang berada di `main`, buat branch dulu sebelum mengubah file.
- Kalau `main` maju, sinkronkan dengan `git merge main` di branch. Jangan rebase, amend, atau force-push branch yang sudah di-push.
- Setelah PR di-merge, branch remote terhapus otomatis (pengaturan repo); hapus juga branch lokal dengan `git branch -d <branch>`.
- Claude Code boleh membuat branch lokal dan commit, tetapi tidak push.

## Alur kerja per issue (test-first)

1. Buat branch sesuai "Aturan branch".
2. Claude Code menulis test lengkap dari bagian **Test case** di issue. Pakai Pest dataset untuk kasus yang berulang, dengan key deskriptif (`'payment review to scheduled' => [...]`). Kalau menemukan kasus penting yang belum tercantum, tambahkan ke test dan sebutkan ke pemilik repo.
3. Pemilik repo menulis implementasi sampai test hijau (untuk Android, lihat "Pembagian peran").
4. `composer check` harus hijau sebelum push; untuk perubahan di `android/`, juga `./gradlew check` dari `android/`.
5. Commit memakai Conventional Commits. Commit test: `test(<modul>): ...`. Biarkan trailer `Co-Authored-By` pada commit yang dibuat Claude Code.
6. **Jangan push dan jangan membuka PR.** Pemilik repo yang push dan membuka PR dengan `Closes #<nomor>`; merge memakai squash.

## Perintah (jalankan dari `backend/`)

| Perintah | Fungsi |
| --- | --- |
| `composer check` | Pint (cek), Larastan, Pest (paralel). Sama dengan CI dan hook pre-push |
| `composer fix` | Pint (format), Larastan, Pest |
| `composer format` | Format dengan Pint |
| `composer analyse` | Larastan level 6 |
| `php artisan test --filter=OrderStatus` | Jalankan test tertentu |

Hook git ada di `.githooks/` (aktif lewat `core.hooksPath`): pre-commit memformat file PHP yang di-stage, pre-push menjalankan `composer check`, post-checkout memindahkan issue ke In Progress di project saat branch issue dibuat. Lingkungan pemilik repo adalah Windows 11 dengan Laravel Herd dan Git Bash; di Git Bash, `vendor/bin/*` tidak bisa dijalankan langsung, jadi pakai `composer exec <tool>` atau script composer.

## Konvensi backend

- **Struktur bawaan Laravel**: `app/{Actions,Data,Enums,Events,Listeners,Exceptions,Models,Policies}`, tanpa folder dasar khusus. Generator, tebakan factory, dan auto-discovery Policy bekerja tanpa konfigurasi tambahan. Penyimpangan dari default Laravel harus disebutkan beserta konsekuensinya dan disetujui pemilik repo lebih dulu.
- **Generator dulu.** Buat file dengan `php artisan make:*` (lihat tabel di engineering-standards). Action dan DTO dengan `make:class Actions/...` dan `make:class Data/...`; exception dengan `make:exception`.
- **Strict types** di semua file PHP (ditegakkan Pint, rule `declare_strict_types`).
- **Enum**: backed enum string; nama case PascalCase (`AwaitingPayment`), nilai snake_case (`'awaiting_payment'`); setiap enum punya `label(): string` berbahasa Indonesia. Ejaan `cancelled` dengan double L.
- **Array bertipe** di docblock (`/** @return list<self> */`) karena Larastan level 6.
- Controller dan komponen Livewire tipis: validasi, panggil satu Action, kembalikan respons. Action punya satu method publik `handle()`, dibungkus `DB::transaction()` bila menulis lebih dari satu tabel.
- Stok hanya berubah lewat `RecordStockMovement`. Status pesanan hanya berubah setelah dicek `OrderStatus::canTransitionTo()`. Enum hanya menjawab transisi mana yang mungkin; siapa yang boleh dan kapan diatur di Action atau Policy.
- Uang dalam rupiah sebagai integer; berat dan qty `decimal(10,3)`. Tidak ada float.
- Test database memakai PostgreSQL, bukan SQLite. Unit test untuk enum dan logika murni di `tests/Unit/<Area>/`; feature test untuk Action dan endpoint di `tests/Feature/<Area>/` (area: Account, Catalog, Inventory, Ordering, Shipping, Store).
- Tidak ada `dd`, `dump`, `ray`, `var_dump` yang ter-commit (dicek arch test).
- Jangan menambah atau mengubah dependency composer tanpa persetujuan.
