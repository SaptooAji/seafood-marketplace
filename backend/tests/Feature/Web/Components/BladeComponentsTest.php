<?php

declare(strict_types=1);

use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;

/*
 * Base Blade components of the buyer web (#167), styled with the role tokens
 * from docs/ui-guidelines.md: x-button, x-input, x-select, x-price, x-badge,
 * and x-empty-state.
 */

const HOSTILE_COMPONENT_TEXT = '<script>alert(1)</script>';

describe('x-price', function () {
    it('formats rupiah with dot thousands separators', function (int $amount, string $expected) {
        $this->blade('<x-price :amount="$amount" />', ['amount' => $amount])
            ->assertSee($expected)
            ->assertDontSee('E+');
    })->with([
        'zero' => [0, 'Rp0'],
        'thousands' => [45000, 'Rp45.000'],
        'millions' => [1500000, 'Rp1.500.000'],
        'hundred million' => [100000000, 'Rp100.000.000'],
    ]);

    it('shows the unit after the price', function () {
        $this->blade('<x-price :amount="185000" unit="kg" />')
            ->assertSeeInOrder(['Rp185.000', '/ kg']);
    });

    it('leaves the unit out when none is given', function () {
        $this->blade('<x-price :amount="185000" />')->assertDontSee('/ ');
    });

    it('passes extra attributes to its wrapper', function () {
        $this->blade('<x-price :amount="1000" class="text-2xl" data-test="harga" />')
            ->assertSee('text-2xl', escape: false)
            ->assertSee('data-test="harga"', escape: false);
    });
});

describe('x-button', function () {
    it('renders a button of type button by default', function () {
        $this->blade('<x-button>Simpan</x-button>')
            ->assertSee('<button', escape: false)
            ->assertSee('type="button"', escape: false)
            ->assertSee('Simpan');
    });

    it('passes the type through', function () {
        $this->blade('<x-button type="submit">Kirim</x-button>')
            ->assertSee('type="submit"', escape: false)
            ->assertDontSee('type="button"', escape: false);
    });

    it('renders a link when given an href', function () {
        $this->blade('<x-button href="/produk">Lihat katalog</x-button>')
            ->assertSee('<a href="/produk"', escape: false)
            ->assertDontSee('<button', escape: false);
    });

    it('styles each variant with the role tokens', function (string $variant, string $class) {
        $this->blade('<x-button :variant="$variant">Aksi</x-button>', ['variant' => $variant])
            ->assertSee($class, escape: false);
    })->with([
        'primary' => ['primary', 'bg-primary-700'],
        'secondary' => ['secondary', 'border-ink'],
        'ghost' => ['ghost', 'text-primary-700'],
    ]);

    it('disables a button with the disabled attribute', function () {
        $this->blade('<x-button disabled>Segera hadir</x-button>')
            ->assertSee('disabled', escape: false)
            ->assertSee('bg-subtle', escape: false);
    });

    it('disables a link with aria-disabled and no href', function () {
        $this->blade('<x-button href="/keranjang" disabled>Keranjang</x-button>')
            ->assertSee('aria-disabled="true"', escape: false)
            ->assertDontSee('href="/keranjang"', escape: false);
    });
});

describe('x-input', function () {
    it('links the label to the field', function () {
        $this->blade('<x-input name="qty" label="Jumlah" />')
            ->assertSee('<label for="qty"', escape: false)
            ->assertSee('id="qty"', escape: false)
            ->assertSee('name="qty"', escape: false)
            ->assertSee('Jumlah');
    });

    it('uses the given id instead of the name', function () {
        $this->blade('<x-input name="qty" id="qty-2" label="Jumlah" />')
            ->assertSee('<label for="qty-2"', escape: false)
            ->assertSee('id="qty-2"', escape: false);
    });

    it('describes the field with its help text', function () {
        $this->blade('<x-input name="qty" label="Jumlah" help="Minimal 1 kg" />')
            ->assertSee('aria-describedby="qty-help"', escape: false)
            ->assertSee('id="qty-help"', escape: false)
            ->assertSee('Minimal 1 kg')
            ->assertDontSee('aria-invalid', escape: false);
    });

    it('marks the field invalid and points to the error', function () {
        $this->blade('<x-input name="qty" label="Jumlah" error="Jumlah kurang dari minimal" />')
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-describedby="qty-error"', escape: false)
            ->assertSee('id="qty-error"', escape: false)
            ->assertSee('Jumlah kurang dari minimal')
            ->assertSee('border-danger-700', escape: false);
    });

    it('reads the error from the validation errors', function () {
        $this->withViewErrors(['qty' => 'Jumlah wajib diisi'])
            ->blade('<x-input name="qty" label="Jumlah" />')
            ->assertSee('Jumlah wajib diisi')
            ->assertSee('aria-invalid="true"', escape: false);
    });

    it('describes the field with both help and error', function () {
        $this->blade('<x-input name="qty" label="Jumlah" help="Minimal 1 kg" error="Terlalu sedikit" />')
            ->assertSee('aria-describedby="qty-help qty-error"', escape: false);
    });

    it('passes extra attributes to the field', function () {
        $this->blade('<x-input name="qty" label="Jumlah" inputmode="decimal" wire:model.live="qty" />')
            ->assertSee('inputmode="decimal"', escape: false)
            ->assertSee('wire:model.live="qty"', escape: false);
    });
});

describe('x-select', function () {
    it('links the label and renders the options', function () {
        $this->blade('<x-select name="kondisi" label="Kondisi"><option value="live">Hidup</option></x-select>')
            ->assertSee('<label for="kondisi"', escape: false)
            ->assertSee('<select', escape: false)
            ->assertSee('id="kondisi"', escape: false)
            ->assertSee('<option value="live">Hidup</option>', escape: false);
    });

    it('marks the select invalid and points to the error', function () {
        $this->blade('<x-select name="kondisi" label="Kondisi" error="Pilih kondisi"><option>Hidup</option></x-select>')
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-describedby="kondisi-error"', escape: false)
            ->assertSee('Pilih kondisi');
    });
});

describe('x-badge', function () {
    it('styles each variant with the role tokens', function (string $variant, array $classes) {
        $view = $this->blade('<x-badge :variant="$variant">Label</x-badge>', ['variant' => $variant]);

        foreach ($classes as $class) {
            $view->assertSee($class, escape: false);
        }
    })->with([
        'neutral' => ['neutral', ['bg-subtle', 'text-ink']],
        'ink' => ['ink', ['bg-ink', 'text-white']],
        'success' => ['success', ['bg-success-50', 'text-primary-800']],
        'warning' => ['warning', ['bg-warning-50', 'text-warning-700']],
        'danger' => ['danger', ['bg-danger-50', 'text-danger-700']],
    ]);

    it('is neutral by default', function () {
        $this->blade('<x-badge>Hidup</x-badge>')
            ->assertSee('bg-subtle', escape: false)
            ->assertSee('Hidup');
    });
});

describe('x-empty-state', function () {
    it('shows the title, the text, and the action', function () {
        $this->blade('<x-empty-state title="Belum ada produk" text="Coba hapus filter." action="Hapus semua filter" href="/produk" />')
            ->assertSeeInOrder(['Belum ada produk', 'Coba hapus filter.', 'Hapus semua filter'])
            ->assertSee('href="/produk"', escape: false);
    });

    it('renders no button without an action', function () {
        $this->blade('<x-empty-state title="Belum ada foto" />')
            ->assertSee('Belum ada foto')
            ->assertDontSee('<a', escape: false)
            ->assertDontSee('<button', escape: false);
    });
});

/**
 * A product of an active store with one variant, loaded as the pages load it.
 */
function cardProduct(): Product
{
    $product = Product::factory()
        ->for(Store::factory()->active()->create(['name' => 'Toko Bahari', 'city' => 'Jakarta Utara']))
        ->create(['name' => 'Kepiting Bakau Super']);
    ProductVariant::factory()->for($product)->create(['price' => 185_000, 'unit' => Unit::Kg]);

    return $product->load(['images', 'variants', 'store']);
}

describe('x-product-card', function () {
    it('shows the cheapest price with x-price', function () {
        $this->blade('<x-product-card :product="$product" />', ['product' => cardProduct()])
            ->assertSeeInOrder(['Kepiting Bakau Super', 'Toko Bahari, Jakarta Utara', 'Mulai', 'Rp185.000', '/ '.Unit::Kg->label()])
            ->assertSee('font-extrabold tracking-tight', escape: false);
    });

    it('shows the distance after the city', function () {
        $this->blade('<x-product-card :product="$product" :distance="2.44" />', ['product' => cardProduct()])
            ->assertSee('Jakarta Utara, 2,4 km');
    });

    it('shows a label over the photo', function () {
        $this->blade('<x-product-card :product="$product" label="Dicek 06.30" />', ['product' => cardProduct()])
            ->assertSeeInOrder(['Dicek 06.30', 'Kepiting Bakau Super']);
    });

    it('shows no label by default', function () {
        $this->blade('<x-product-card :product="$product" />', ['product' => cardProduct()])
            ->assertDontSee('Dicek');
    });

    it('fills a product without photos with the compact empty state', function () {
        $this->blade('<x-product-card :product="$product" />', ['product' => cardProduct()])
            ->assertSee('Belum ada foto')
            ->assertSee('bg-subtle', escape: false)
            ->assertDontSee('<img', escape: false);
    });
});

it('escapes text given to the components', function (string $template) {
    $this->blade($template, ['text' => HOSTILE_COMPONENT_TEXT])
        ->assertDontSee(HOSTILE_COMPONENT_TEXT, escape: false)
        ->assertSee(HOSTILE_COMPONENT_TEXT);
})->with([
    'button slot' => ['<x-button>{{ $text }}</x-button>'],
    'input label' => ['<x-input name="q" :label="$text" />'],
    'input help' => ['<x-input name="q" label="Cari" :help="$text" />'],
    'input error' => ['<x-input name="q" label="Cari" :error="$text" />'],
    'select label' => ['<x-select name="q" :label="$text"><option>A</option></x-select>'],
    'price unit' => ['<x-price :amount="1000" :unit="$text" />'],
    'badge slot' => ['<x-badge>{{ $text }}</x-badge>'],
    'empty state title' => ['<x-empty-state :title="$text" />'],
    'empty state text' => ['<x-empty-state title="Kosong" :text="$text" />'],
    'empty state action' => ['<x-empty-state title="Kosong" :action="$text" href="/produk" />'],
]);
