# Standar Keamanan: Marketplace Hasil Laut

Dokumen ini adalah aturan keamanan untuk semua kode di repository. Setiap issue mengisi bagian **Keamanan** berdasarkan dokumen ini, dan setiap PR direview terhadap checklist di bawah.

Aturan ditulis agar bisa dicek, sebagian besar dengan test. Setiap aturan punya kode (`AC-1`, `API-3`) supaya bisa dirujuk di issue, PR, dan temuan audit.

## Acuan

| Standar | Dipakai untuk |
| --- | --- |
| [OWASP ASVS 4.0](https://owasp.org/www-project-application-security-verification-standard/), level 2 | Acuan utama: autentikasi, sesi, kontrol akses, validasi, file, log, konfigurasi |
| [OWASP API Security Top 10 (2023)](https://owasp.org/API-Security/) | REST API `/api/v1` untuk Android |
| [OWASP Top 10 for LLM Applications](https://genai.owasp.org/llm-top-10/) | Fitur saran deskripsi dengan AI (Gemini) |
| [OWASP MASVS](https://mas.owasp.org/MASVS/), dasar | Aplikasi Android (Tahap 4) |

Dokumen ini tidak menyalin standar itu. Isinya aturan yang berlaku untuk aplikasi ini, dengan rujukan ke bagian standar yang relevan.

## Status

Setiap aturan diberi status terhadap kode di `main` saat dokumen ini ditulis (Tahap 3):

- ✅ sudah dipenuhi dan dibuktikan test
- ⚠️ sebagian, atau dipenuhi tanpa test
- ❌ belum dipenuhi

Aturan ⚠️ dan ❌ menjadi bahan audit Tahap 1–3. Status diperbarui di PR yang memperbaikinya.

## 1. Autentikasi (ASVS V2, API2)

| Kode | Aturan | Status |
| --- | --- | --- |
| AUTH-1 | Login email + password dibatasi per kombinasi email dan IP (`throttle:login`); register dan login Google dibatasi per IP | ✅ |
| AUTH-2 | Password minimal 8 karakter, di-hash bcrypt (cast `hashed`), tidak pernah tampil di respons atau log | ✅ |
| AUTH-3 | Pesan gagal login sama untuk email tidak terdaftar, password salah, dan akun Google tanpa password | ⚠️ kasus email tidak terdaftar belum dites |
| AUTH-4 | Token Google diverifikasi: `aud` termasuk client id yang diizinkan, `iss` Google, belum kedaluwarsa, email terverifikasi | ✅ |
| AUTH-5 | Password dicek terhadap daftar password bocor (`Password::uncompromised()`) | ❌ |
| AUTH-6 | Pembeli web hanya login dengan Google; tidak ada form password di web pembeli | ✅ |

## 2. Sesi dan token (ASVS V3, API2)

| Kode | Aturan | Status |
| --- | --- | --- |
| SES-1 | Token Sanctum punya masa berlaku (`sanctum.expiration`) dan prefix untuk secret scanning (`SANCTUM_TOKEN_PREFIX`) | ❌ keduanya kosong |
| SES-2 | Logout mencabut token yang sedang dipakai saja; token perangkat lain tetap | ✅ |
| SES-3 | Token diberi nama perangkat, sehingga bisa dicabut per perangkat | ✅ |
| SES-4 | Cookie sesi web: `http_only`, `same_site=lax`, `secure` di produksi | ⚠️ `secure` belum diatur untuk produksi |
| SES-5 | Sesi web diregenerasi setelah login (mencegah session fixation); `Auth::login` melakukannya | ⚠️ belum dites |

## 3. Kontrol akses (ASVS V4, API1, API3, API5)

Ini area paling penting di marketplace multi-toko: satu toko tidak boleh melihat atau mengubah data toko lain.

| Kode | Aturan | Status |
| --- | --- | --- |
| AC-1 | Setiap endpoint seller mengambil data lewat toko pengguna (`currentStore()->products()`), bukan `Model::find($id)` | ✅ |
| AC-2 | Data toko lain dibalas **404**, bukan 403, agar keberadaannya tidak bocor; setiap endpoint punya test untuk ini | ✅ |
| AC-3 | Setiap aksi tulis dicek Policy (`Gate::authorize`), walaupun data sudah difilter per toko | ✅ |
| AC-4 | Peran toko dibedakan: staff tidak melihat rekening dan tidak mengelola member (PRD) | ✅ rekening; member belum ada |
| AC-5 | Panel `/admin` hanya untuk role platform; panel `/seller` hanya untuk anggota toko aktif; tenant dicek ulang di setiap request (`canAccessTenant`) | ✅ |
| AC-6 | Field yang tidak boleh diubah pengguna tidak bisa dikirim lewat request: `store_id`, `stock`, `blocked_at`, `status` toko. Input dibaca lewat Form Request dan DTO, bukan `$request->all()` | ✅ |
| AC-7 | Toko `suspended` tidak bisa memakai fitur seller di API (panel sudah memblokirnya lewat `canAccessTenant`) | ⚠️ perlu dicek per endpoint |
| AC-8 | Super Admin dan Admin dibedakan di Policy, bukan hanya di menu | ⚠️ perlu dicek |

## 4. Validasi input dan file (ASVS V5, V12)

| Kode | Aturan | Status |
| --- | --- | --- |
| VAL-1 | Semua input lewat Form Request dengan tipe, panjang maksimal, dan rentang; aturan bisnis di Action dengan exception yang menyebut field | ✅ |
| VAL-2 | Angka uang integer, berat dan qty string desimal; tidak ada float | ✅ |
| VAL-3 | Query database memakai binding; `whereRaw`/`selectRaw` hanya dengan placeholder `?` | ✅ |
| VAL-4 | Upload foto: ekstensi dan ukuran divalidasi, lalu **di-decode dan di-encode ulang** ke WebP (metadata EXIF/GPS terbuang, file palsu ditolak); nama file UUID, bukan nama dari pengguna | ✅ |
| VAL-5 | File unggahan disimpan di luar web root dan disajikan lewat disk `public` hanya untuk foto produk; bukti transfer (Tahap 5) di disk privat dengan URL bertanda tangan | ✅ foto; Tahap 5 belum |
| VAL-6 | Teks dari pengguna dirender ter-escape (`{{ }}`); markdown (standar kualitas) dirender dengan HTML mentah dimatikan | ⚠️ web belum dibuat |
| VAL-7 | JSON-LD dan data yang disisipkan ke `<script>` memakai `Js::from` atau `JSON_HEX_TAG` | ❌ web belum dibuat |

## 5. Eksposur data (ASVS V8, API3)

| Kode | Aturan | Status |
| --- | --- | --- |
| DATA-1 | Respons API selalu lewat API Resource dengan field eksplisit; model tidak pernah dikembalikan langsung | ✅ |
| DATA-2 | Data sensitif toko (rekening) hanya untuk owner; tidak pernah di web publik, JSON-LD, atau log | ✅ API; web belum |
| DATA-3 | Data pembeli (HP, alamat) hanya untuk toko yang menerima pesanan, hanya selama pesanan berjalan (Tahap 5) | ❌ belum dibuat |
| DATA-4 | Koordinat toko tampil dengan presisi yang dibutuhkan (peta toko); koordinat pembeli tidak pernah disimpan dari browser, hanya dipakai untuk menghitung jarak | ⚠️ web belum dibuat |
| DATA-5 | Pesan error di produksi umum (`APP_DEBUG=false`); detail hanya di log | ⚠️ belum ada konfigurasi produksi |
| DATA-6 | Paginasi dengan batas atas di semua daftar API (mencegah dump data) | ✅ |

## 6. Rate limit dan biaya (API4, API6, LLM10)

| Kode | Aturan | Status |
| --- | --- | --- |
| RATE-1 | Endpoint autentikasi dibatasi (AUTH-1) | ✅ |
| RATE-2 | Semua endpoint API terautentikasi punya limiter umum per pengguna (mis. 60 per menit) | ❌ |
| RATE-3 | Endpoint yang mahal (upload foto, AI) punya limiter sendiri; fitur AI dibatasi per toko, per pengguna, dan global harian | ❌ dibuat di #119 |
| RATE-4 | Jumlah foto per produk dan varian per request dibatasi | ✅ |

## 7. Rahasia dan konfigurasi (ASVS V14, API8)

| Kode | Aturan | Status |
| --- | --- | --- |
| CONF-1 | Rahasia hanya di `.env` (di-ignore git); `.env.example` berisi nama variabel dengan nilai kosong | ✅ |
| CONF-2 | Rahasia tidak pernah di URL, log, pesan exception, fixture test, atau aplikasi Android | ⚠️ ID token Google dikirim sebagai query `tokeninfo` (bentuk resmi endpoint itu); pastikan URL-nya tidak ikut tercatat saat gagal |
| CONF-3 | Secret scanning dan push protection GitHub aktif | ❌ #123 |
| CONF-4 | Produksi: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL` minimal `warning`, HTTPS dengan HSTS, `SESSION_SECURE_COOKIE=true` | ❌ Tahap 7 |
| CONF-5 | Header keamanan web: `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options` (kecuali yang dibutuhkan), CSP di Tahap 7 | ❌ |
| CONF-6 | CORS tidak dibuka untuk API karena Android tidak memakai browser; bila dibutuhkan, origin dibatasi eksplisit | ✅ tidak ada `config/cors.php` |
| CONF-7 | Perintah artisan destruktif dilarang di produksi (`DB::prohibitDestructiveCommands`); `Model::shouldBeStrict` di luar produksi | ✅ |

## 8. Dependency (ASVS V14.2, API10)

| Kode | Aturan | Status |
| --- | --- | --- |
| DEP-1 | `composer audit` dan `npm audit` di CI | ❌ #123 |
| DEP-2 | Dependabot untuk composer, npm, dan GitHub Actions | ❌ #123 |
| DEP-3 | Package baru butuh persetujuan pemilik repo; dicek reputasi, perawatan, dan lisensinya | ✅ |
| DEP-4 | Memanggil API pihak ketiga dengan `Http` dan timeout; respons divalidasi, bukan dipercaya | ✅ |

## 9. Log dan audit (ASVS V7)

| Kode | Aturan | Status |
| --- | --- | --- |
| LOG-1 | Perubahan oleh admin dan data master tercatat di activity log (toko, kategori, atribut); produk ditambah saat moderasi (#97) | ⚠️ |
| LOG-2 | Stok punya jejak lengkap di `stock_movements`, termasuk siapa yang mencatat | ✅ |
| LOG-3 | Log tidak memuat password, token, kunci API, nomor rekening, atau isi prompt AI | ⚠️ perlu dicek |

## 10. Fitur AI (LLM Top 10)

Rincian di #118 dan #119.

| Kode | Aturan | Status |
| --- | --- | --- |
| AI-1 | Kunci API hanya di server, dikirim lewat header (`x-goog-api-key`), dibatasi ke Generative Language API | ❌ #118 |
| AI-2 | Prompt injection (LLM01): instruksi di `systemInstruction`; isian penjual dikirim sebagai blok data JSON terpisah | ❌ #118 |
| AI-3 | Keluaran tidak dipercaya (LLM05): divalidasi skema dan panjang, HTML dibuang, dirender ter-escape | ❌ #118 |
| AI-4 | Data minimal (LLM02): tanpa rekening, kontak, alamat, atau data pembeli | ❌ #118 |
| AI-5 | Hasil AI hanya saran; tidak ada yang tersimpan tanpa tindakan penjual (LLM06) | ❌ #119 |
| AI-6 | Kuota per toko, per pengguna, dan global (LLM10) | ❌ #119 |
| AI-7 | Tier gratis Gemini hanya untuk demo; produksi memakai tier berbayar | ❌ README |

## 11. Android (Tahap 4, MASVS dasar)

Dirinci saat issue Tahap 4 ditulis. Aturan dasarnya:

- Token disimpan terenkripsi (Android Keystore / EncryptedDataStore), tidak di `SharedPreferences` biasa.
- Tidak ada rahasia di APK selain client id publik; semua kunci pihak ketiga lewat backend.
- Hanya HTTPS (`network_security_config` tanpa cleartext di build rilis); log HTTP mati di build rilis.
- Build rilis memakai R8/minify.

## Checklist issue dan review PR

Dipakai untuk mengisi bagian **Keamanan** di issue dan saat mereview PR. Jawab yang relevan saja.

1. **Siapa boleh?** Peran mana (pembeli, owner, staff, admin, super admin) yang boleh memakai fitur ini? Bagaimana dengan data toko lain (404)? Toko `suspended`?
2. **Input apa?** Tipe, panjang maksimal, rentang, format; field yang tidak boleh dikirim pengguna. File: tipe, ukuran, encode ulang.
3. **Data apa yang keluar?** Field di respons atau halaman; adakah data pribadi atau sensitif? Untuk siapa?
4. **Perlu rate limit?** Apakah endpoint ini mahal (file, API luar) atau bisa dipakai menebak data?
5. **Rahasia apa yang dipakai?** Dari mana dibaca, dan pastikan tidak tercatat di log, URL, atau respons.
6. **Apa yang dicatat?** Perlu activity log? Log tidak memuat data sensitif.
7. **Test keamanan:** minimal satu kasus akses data toko lain atau peran yang tidak berhak, dan satu kasus input berbahaya bila ada input teks atau file.

## Klasifikasi temuan

| Tingkat | Contoh | Kapan diperbaiki |
| --- | --- | --- |
| Kritis | Akses data toko lain, bypass autentikasi, rahasia bocor | Segera, sebelum pekerjaan lain |
| Tinggi | Data pribadi tampil ke pihak yang tidak berhak, upload berbahaya diterima, tanpa rate limit di endpoint mahal | Sebelum rilis tahap |
| Sedang | Token tanpa masa berlaku, header keamanan tidak ada | Rilis tahap atau patch berikutnya |
| Rendah | Pesan error terlalu rinci di lingkungan non-produksi, hardening tambahan | Patch atau tahap berikutnya |

Setiap temuan menjadi issue `fix(security): ...` dengan label `fix` dan tingkatnya di deskripsi. Detail celah yang belum diperbaiki tidak ditulis di issue publik; cukup ringkasan dan rujukan kode aturan, detail lewat private vulnerability reporting (lihat `SECURITY.md`).

## Jadwal

| Kapan | Apa |
| --- | --- |
| Setiap issue | Bagian **Keamanan** diisi; test keamanan ditulis bersama test lain |
| Setiap PR | Review terhadap checklist; CI menjalankan audit dependency (#123) |
| Akhir Tahap 3, sebelum v0.3.0 | Audit Tahap 1–3 terhadap dokumen ini; temuan kritis dan tinggi diperbaiki sebelum rilis |
| Akhir setiap tahap berikutnya | Tinjau status aturan yang berkaitan dengan tahap itu |
| Tahap 7, sebelum rilis publik | Hardening produksi (CONF-4, CONF-5), OWASP ZAP baseline untuk web, cek APK (MASVS dasar) |
