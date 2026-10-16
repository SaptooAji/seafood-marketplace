<?php

declare(strict_types=1);

use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * "Produk serupa" and "Lapak lain yang menjual" under the product page (#173).
 *
 * Kepiting
 *   ├─ Bakau     (Kepiting Bakau Super from Toko Bahari, the product shown)
 *   └─ Rajungan  (sibling category)
 * Udang          (unrelated root category)
 */
beforeEach(function () {
    $this->kepiting = Category::factory()->create(['name' => 'Kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau']);
    $this->rajungan = Category::factory()->childOf($this->kepiting)->create(['name' => 'Rajungan']);
    $this->udang = Category::factory()->create(['name' => 'Udang']);

    $this->store = Store::factory()->active()->create(['name' => 'Toko Bahari', 'city' => 'Jakarta Utara']);

    $this->product = relatedProduct($this->store, $this->bakau, ['name' => 'Kepiting Bakau Super', 'slug' => 'kepiting-bakau-super'], 185_000);
});

/**
 * A product buyers can see, with one active variant at the given price.
 *
 * @param  array<string, mixed>  $attributes
 */
function relatedProduct(Store $store, Category $category, array $attributes = [], int $price = 100_000, Unit $unit = Unit::Kg): Product
{
    $product = Product::factory()->for($store)->for($category)->create($attributes);
    ProductVariant::factory()->for($product)->create(['price' => $price, 'unit' => $unit]);

    return $product;
}

/**
 * The HTML of one section of the product page, or an empty string when it is not rendered.
 */
function relatedSection(string $html, string $id): string
{
    if (! str_contains($html, 'aria-labelledby="'.$id.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$id.'"'), '</section>');
}

function relatedPageHtml(): string
{
    return test()->get('/produk/kepiting-bakau-super')->assertOk()->getContent();
}

describe('similar products', function () {
    it('shows other products of the same category, not the product itself', function () {
        foreach (['Bakau Satu', 'Bakau Dua', 'Bakau Tiga'] as $name) {
            relatedProduct(Store::factory()->active()->create(), $this->bakau, ['name' => $name]);
        }

        $section = relatedSection(relatedPageHtml(), 'produk-serupa');

        expect($section)
            ->toContain('Produk serupa')
            ->toContain('Bakau Satu')
            ->toContain('Bakau Dua')
            ->toContain('Bakau Tiga')
            ->not->toContain('Kepiting Bakau Super')
            ->not->toContain(route('products.show', 'kepiting-bakau-super').'"');
    });

    it('fills up from the sibling categories, the same category first and newest first', function () {
        relatedProduct($this->store, $this->bakau, ['name' => 'Bakau Lain', 'created_at' => now()->subDays(10)]);
        foreach (range(1, 5) as $day) {
            relatedProduct(Store::factory()->active()->create(), $this->rajungan, ['name' => "Rajungan Hari {$day}", 'created_at' => now()->subDays($day)]);
        }
        relatedProduct(Store::factory()->active()->create(), $this->udang, ['name' => 'Udang Vaname', 'created_at' => now()]);

        $section = relatedSection(relatedPageHtml(), 'produk-serupa');

        expect($section)
            ->toContain('Bakau Lain')
            ->toContain('Rajungan Hari 1')
            ->toContain('Rajungan Hari 2')
            ->toContain('Rajungan Hari 3')
            ->not->toContain('Rajungan Hari 4')
            ->not->toContain('Rajungan Hari 5')
            ->not->toContain('Udang Vaname')
            ->and(strpos($section, 'Bakau Lain'))->toBeLessThan(strpos($section, 'Rajungan Hari 1'))
            ->and(strpos($section, 'Rajungan Hari 1'))->toBeLessThan(strpos($section, 'Rajungan Hari 2'));
    });

    it('shows at most 4 products', function () {
        foreach (range(1, 6) as $number) {
            relatedProduct(Store::factory()->active()->create(), $this->bakau, ['name' => "Bakau Nomor {$number}."]);
        }

        expect(preg_match_all('/Bakau Nomor \d\./', relatedSection(relatedPageHtml(), 'produk-serupa')))->toBe(4);
    });

    it('does not fill up from other root categories when the product is in a root category', function () {
        $this->product->update(['category_id' => $this->kepiting->id]);
        relatedProduct(Store::factory()->active()->create(), $this->udang, ['name' => 'Udang Vaname']);

        expect(relatedPageHtml())->not->toContain('Udang Vaname');
    });

    it('shows each product with the product card', function () {
        relatedProduct(Store::factory()->active()->create(['name' => 'Lapak Bu Sari', 'city' => 'Muara Baru']), $this->bakau, ['name' => 'Bakau Jumbo'], 210_000);

        expect(relatedSection(relatedPageHtml(), 'produk-serupa'))
            ->toContain('Lapak Bu Sari, Muara Baru')
            ->toContain('Rp210.000')
            ->toContain('/ '.Unit::Kg->label());
    });
});

describe('other stores', function () {
    it('lists other stores selling the category, cheapest first, with city and unit', function () {
        $sari = Store::factory()->active()->create(['name' => 'Lapak Bu Sari', 'city' => 'Muara Baru']);
        relatedProduct($sari, $this->bakau, [], 180_000);
        relatedProduct($sari, $this->bakau, [], 195_000);

        $indah = Store::factory()->active()->create(['name' => 'Pantai Indah', 'city' => 'Cilincing']);
        relatedProduct($indah, $this->bakau, [], 178_000, Unit::Piece);

        $section = relatedSection(relatedPageHtml(), 'lapak-lain');

        expect($section)
            ->toContain('Lapak lain yang menjual Bakau')
            ->toContain('href="'.route('stores.show', $sari->slug).'"')
            ->toContain('href="'.route('stores.show', $indah->slug).'"')
            ->not->toContain('Rp195.000')
            ->and(strpos($section, 'Pantai Indah'))->toBeLessThan(strpos($section, 'Lapak Bu Sari'));

        expect(Str::before($section, 'Lapak Bu Sari'))->toContain('Cilincing')->toContain('Rp178.000')->toContain('/ '.Unit::Piece->label())
            ->and(Str::after($section, 'Lapak Bu Sari'))->toContain('Muara Baru')->toContain('Rp180.000')->toContain('/ '.Unit::Kg->label());
    });

    it('leaves out the store of this product, even when it is the cheapest', function () {
        relatedProduct($this->store, $this->bakau, ['name' => 'Bakau Murah'], 90_000);
        relatedProduct(Store::factory()->active()->create(['name' => 'Lapak Bu Sari']), $this->bakau, [], 180_000);

        expect(relatedSection(relatedPageHtml(), 'lapak-lain'))
            ->toContain('Lapak Bu Sari')
            ->not->toContain('Toko Bahari')
            ->not->toContain('Rp90.000');
    });

    it('only counts the prices of active variants in this category', function () {
        $sari = Store::factory()->active()->create(['name' => 'Lapak Bu Sari']);
        $product = relatedProduct($sari, $this->bakau, [], 190_000);
        ProductVariant::factory()->for($product)->create(['price' => 100_000, 'is_active' => false]);
        ProductVariant::factory()->for($product)->create(['price' => 110_000])->delete();
        relatedProduct($sari, $this->rajungan, [], 50_000);

        expect(relatedSection(relatedPageHtml(), 'lapak-lain'))
            ->toContain('Rp190.000')
            ->not->toContain('Rp100.000')
            ->not->toContain('Rp110.000')
            ->not->toContain('Rp50.000');
    });

    it('shows at most 3 stores', function () {
        foreach (range(1, 5) as $number) {
            relatedProduct(Store::factory()->active()->create(['name' => "Lapak Nomor {$number}."]), $this->bakau);
        }

        expect(preg_match_all('/Lapak Nomor \d\./', relatedSection(relatedPageHtml(), 'lapak-lain')))->toBe(3);
    });
});

describe('hidden products', function () {
    it('leaves products buyers may not see out of both sections', function (Closure $hide) {
        $store = Store::factory()->active()->create(['name' => 'Lapak Tersembunyi']);
        $product = relatedProduct($store, $this->bakau, ['name' => 'Bakau Tersembunyi']);
        $hide($product);

        $html = relatedPageHtml();

        expect($html)
            ->not->toContain('Bakau Tersembunyi')
            ->not->toContain('Lapak Tersembunyi')
            ->not->toContain('aria-labelledby="produk-serupa"')
            ->not->toContain('aria-labelledby="lapak-lain"');
    })->with([
        'from a suspended store' => [fn (Product $p) => $p->store->forceFill(['status' => 'suspended'])->save()],
        'from a pending store' => [fn (Product $p) => $p->store->forceFill(['status' => 'pending'])->save()],
        'from a rejected store' => [fn (Product $p) => $p->store->forceFill(['status' => 'rejected'])->save()],
        'blocked' => [fn (Product $p) => $p->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->save()],
        'inactive' => [fn (Product $p) => $p->update(['is_active' => false])],
        'without an active variant' => [fn (Product $p) => $p->variants()->update(['is_active' => false])],
        'deleted' => [fn (Product $p) => $p->delete()],
    ]);
});

describe('empty', function () {
    it('renders neither section, not even the headings, when there is nothing to show', function () {
        relatedProduct(Store::factory()->active()->create(), $this->udang, ['name' => 'Udang Vaname']);

        expect(relatedPageHtml())
            ->not->toContain('Produk serupa')
            ->not->toContain('Lapak lain yang menjual');
    });
});

describe('security', function () {
    it('never shows bank details or coordinates of the other stores', function () {
        $other = Store::factory()->active()->create([
            'lat' => '-6.1234567',
            'lng' => '106.7654321',
            'bank_name' => 'Bank Rahasia',
            'bank_account_no' => '9876543210',
            'bank_account_name' => 'Pemilik Rahasia',
        ]);
        relatedProduct($other, $this->bakau);

        expect(relatedPageHtml())
            ->toContain('aria-labelledby="lapak-lain"')
            ->not->toContain('Bank Rahasia')
            ->not->toContain('9876543210')
            ->not->toContain('Pemilik Rahasia')
            ->not->toContain('-6.1234567')
            ->not->toContain('106.7654321');
    });

    it('escapes the names of other products and stores', function () {
        $other = Store::factory()->active()->create(['name' => 'Lapak <script>alert(1)</script>']);
        relatedProduct($other, $this->bakau, ['name' => 'Bakau <img src=x onerror=alert(2)>']);

        expect(relatedPageHtml())
            ->toContain(e('Lapak <script>alert(1)</script>'))
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<img src=x onerror=alert(2)>');
    });
});

describe('performance', function () {
    it('runs the same number of queries for 2 and 20 similar products and stores', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            relatedPageHtml();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $add = function (int $number): void {
            foreach (range(1, $number) as $i) {
                relatedProduct(Store::factory()->active()->create(), $i % 2 === 0 ? $this->bakau : $this->rajungan);
            }
        };

        $add(2);
        $few = $count();

        $add(18);

        expect($count())->toBe($few);
    });

    it('does not look up the related products again when a variant is chosen', function () {
        relatedProduct(Store::factory()->active()->create(['name' => 'Lapak Bu Sari']), $this->bakau, ['name' => 'Bakau Jumbo']);
        $second = ProductVariant::factory()->for($this->product)->create(['price' => 240_000]);

        $page = Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->assertSee('Bakau Jumbo')
            ->assertSee('Lapak Bu Sari');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $page->set('variantId', $second->id)->assertSet('variantId', $second->id);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        expect($queries->filter(fn (string $sql): bool => str_contains($sql, '"category_id"')))->toBeEmpty();
    });
});
