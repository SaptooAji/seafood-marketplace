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

## Test case
<!-- Spesifikasi yang bisa dijalankan. Ditulis sebagai test lebih dulu, lalu implementasi sampai hijau. -->
**Valid**
- ...

**Ditolak / error**
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
