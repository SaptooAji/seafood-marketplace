<x-filament-panels::page>
    @if ($this->loadedStock === [])
        <p class="text-sm text-gray-500">Belum ada varian aktif. Tambahkan produk dan varian di menu Produk.</p>
    @else
        <p class="text-sm text-gray-500">Hitung stok yang ada sekarang. Ubah hanya varian yang berbeda, lalu Simpan; atau pilih "Sama seperti kemarin" bila semua sama.</p>

        {{ $this->form }}
    @endif
</x-filament-panels::page>
