---
name: Task
about: Satu unit kerja yang selesai dalam satu PR
title: "<jenis>(<modul>): <ringkasan>"
labels: []
---

## Tujuan
<!-- Satu-dua kalimat: apa yang dibangun dan kenapa. -->

## Kriteria selesai
- [ ] ...
- [ ] Semua test case di bawah ada dan hijau
- [ ] `composer check` hijau di lokal dan CI

## Keamanan
<!-- Wajib. Jawab yang relevan dari checklist docs/security-standards.md dan rujuk kode aturannya (AC-2, VAL-4, ...). Tulis "Tidak ada dampak keamanan" hanya untuk perubahan dokumen atau konfigurasi tanpa input, data, atau rahasia. -->
- **Siapa boleh:** peran yang boleh; data toko lain (404); toko `suspended`
- **Input:** tipe, panjang, rentang; field yang tidak boleh dikirim pengguna; file
- **Data keluar:** field di respons atau halaman; data pribadi atau sensitif dan untuk siapa
- **Rate limit:** perlu atau tidak, dan alasannya
- **Rahasia dan log:** rahasia yang dipakai; apa yang dicatat dan apa yang tidak boleh

## Test case
<!-- Spesifikasi yang bisa dijalankan. Ditulis sebagai test lebih dulu, lalu implementasi sampai hijau. -->
**Valid**
- ...

**Ditolak / error**
- ...

**Keamanan**
<!-- Minimal: akses data toko lain atau peran yang tidak berhak; input berbahaya bila ada teks atau file. -->
- ...

**Edge case**
- ...

## Cara mulai
```
git switch main && git pull
git switch -c <jenis>/<nomor>-<ringkas>
php artisan make:...
```

## Petunjuk
<!-- Pola kode, contoh dari domain lain, rujukan ke docs/prd.md. Bukan solusi lengkap. -->

## Di luar scope
- ...
